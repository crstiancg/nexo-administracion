<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\ComprobanteSerie;
use App\Models\Pedido;
use App\Models\User;
use App\Support\NumeroALetras;
use CodersFree\LaravelGreenter\DTOs\SendResultDTO;
use CodersFree\LaravelGreenter\Facades\Greenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Facturación electrónica: boletas y facturas de los pedidos, con Greenter.
 *
 * - Se emite sobre un pedido confirmado o entregado (lo que ya se vendió),
 *   con la serie de la sede que vendió. El número se toma con la serie
 *   bloqueada y no se reutiliza: un rechazo se corrige emitiendo otro.
 * - El envío a SUNAT va FUERA de la transacción de la venta: si SUNAT no
 *   responde, la venta queda hecha y el comprobante en "error", para
 *   reenviarlo con el mismo número.
 * - Los precios ya incluyen el IGV (App\Support\Igv): se desglosan por línea
 *   y el descuento del pedido va como descuento global que afecta la base.
 */
class Facturacion
{
    /** Desde este total SUNAT exige identificar al cliente de una boleta. */
    public const BOLETA_MONTO_IDENTIFICADO = 700;

    /** Catálogo 06 de SUNAT. */
    private const TIPO_DOC_CLIENTE = [Cliente::DNI => '1', Cliente::CE => '4', Cliente::RUC => '6'];

    /**
     * Si el pedido puede llevar ese comprobante. Lanza ValidationException
     * con el motivo (para el formulario) en `comprobante`.
     */
    public function validar(Pedido $pedido, string $tipo, ?Cliente $cliente = null, ?float $total = null): void
    {
        $cliente ??= $pedido->cliente;
        $total ??= (float) $pedido->total;

        if (! array_key_exists($tipo, Comprobante::TIPOS)) {
            $this->falla('Tipo de comprobante inválido.');
        }

        if ($tipo === Comprobante::FACTURA) {
            if (! $cliente || $cliente->tipo_documento !== Cliente::RUC || ! preg_match('/^\d{11}$/', (string) $cliente->numero_documento)) {
                $this->falla('Para emitir factura el cliente tiene que tener RUC.');
            }
        }

        if ($tipo === Comprobante::BOLETA && $total >= self::BOLETA_MONTO_IDENTIFICADO
            && (! $cliente || blank($cliente->numero_documento))) {
            $this->falla('Desde S/ '.self::BOLETA_MONTO_IDENTIFICADO.' la boleta tiene que llevar el DNI (o documento) del cliente.');
        }
    }

    /**
     * Numera, arma y envía el comprobante del pedido. Devuelve el comprobante
     * con su estado (aceptado, rechazado o error si no llegó a SUNAT).
     */
    public function emitir(Pedido $pedido, string $tipo, ?User $usuario): Comprobante
    {
        $pedido->loadMissing(['cliente', 'comprobantes', 'items.variante.producto', 'items.variante.unidad', 'items.variante.color']);

        if (! in_array($pedido->estado, [Pedido::CONFIRMADO, Pedido::ENTREGADO], true)) {
            $this->falla('Sólo se emite comprobante de un pedido confirmado o entregado.');
        }
        if ($vigente = $pedido->comprobanteVigente()) {
            $this->falla("Este pedido ya tiene la {$vigente->numero()} ({$vigente->estado}).");
        }

        $this->validar($pedido, $tipo);

        $comprobante = $this->numerar($pedido, $tipo, $usuario);

        return $this->enviar($comprobante, $pedido);
    }

    /**
     * Reenvía el MISMO comprobante (mismo número y XML) que no llegó a SUNAT.
     */
    public function reenviar(Comprobante $comprobante): Comprobante
    {
        if ($comprobante->estado !== Comprobante::ERROR) {
            $this->falla("La {$comprobante->numero()} está {$comprobante->estado}: no hace falta reenviarla.");
        }

        $xml = $comprobante->xml_path ? Storage::disk('local')->get($comprobante->xml_path) : null;
        if (! $xml) {
            // Nunca se llegó a armar: se arma de nuevo con el mismo número.
            return $this->enviar($comprobante, $comprobante->pedido()->with(['cliente', 'items.variante.producto', 'items.variante.unidad', 'items.variante.color'])->firstOrFail());
        }

        return $this->registrarRespuesta($comprobante, Greenter::sendXml('invoice', $xml), $xml);
    }

    private function numerar(Pedido $pedido, string $tipo, ?User $usuario): Comprobante
    {
        $datos = $this->totales($pedido);
        $cliente = $this->cliente($pedido, $tipo);

        return DB::transaction(function () use ($pedido, $tipo, $usuario, $datos, $cliente) {
            $serie = ComprobanteSerie::query()
                ->where('sede_id', $pedido->sede_id)
                ->where('tipo', $tipo)
                ->lockForUpdate()
                ->first();

            if (! $serie) {
                $this->falla('Esta sede no tiene serie para ese comprobante: configurala en Sedes.');
            }

            $serie->correlativo++;
            $serie->save();

            return Comprobante::query()->forceCreate([
                'pedido_id' => $pedido->id,
                'sede_id' => $pedido->sede_id,
                'tipo' => $tipo,
                'serie' => $serie->serie,
                'correlativo' => $serie->correlativo,
                'fecha_emision' => now(),
                'cliente_tipo_doc' => $cliente['tipoDoc'],
                'cliente_num_doc' => $cliente['numDoc'],
                'cliente_nombre' => $cliente['rznSocial'],
                'op_gravada' => $datos['mtoOperGravadas'],
                'igv' => $datos['mtoIGV'],
                'descuento' => $datos['descuento'],
                'total' => $datos['mtoImpVenta'],
                'total_letras' => NumeroALetras::convertir($datos['mtoImpVenta'], 'SOLES'),
                'estado' => Comprobante::PENDIENTE,
                'user_id' => $usuario?->id,
            ]);
        });
    }

    private function enviar(Comprobante $comprobante, Pedido $pedido): Comprobante
    {
        // El establecimiento (ficha RUC) es el de la sede que vendió; el
        // domicilio del emisor sigue siendo el fiscal de config/greenter.php.
        config(['greenter.company.address.codLocal' => $pedido->sede()->value('codigo_sunat') ?: '0000']);

        try {
            $resultado = Greenter::send('invoice', $this->documento($comprobante, $pedido));
        } catch (\Throwable $e) {
            // Certificado ilegible, configuración incompleta…: el número ya
            // está tomado, queda en error para reenviarlo al corregirlo.
            report($e);
            $comprobante->forceFill([
                'estado' => Comprobante::ERROR,
                'sunat_mensaje' => 'No se pudo armar o firmar el comprobante: '.$e->getMessage(),
            ])->save();

            return $comprobante;
        }

        return $this->registrarRespuesta($comprobante, $resultado);
    }

    private function registrarRespuesta(Comprobante $comprobante, SendResultDTO $resultado, ?string $xmlPrevio = null): Comprobante
    {
        $nombre = "{$comprobante->serie}-{$comprobante->correlativo}";
        $sunat = $resultado->getSunatResponse();

        if ($xml = $resultado->getXml()) {
            $comprobante->xml_path = "sunat/xml/{$nombre}.xml";
            Storage::disk('local')->put($comprobante->xml_path, $xml);
        }
        $comprobante->hash = $resultado->getHash() ?: $comprobante->hash ?: $this->hashDe($xmlPrevio);

        if ($sunat?->getSuccess()) {
            $cdr = $sunat->getCdrResponse();
            if ($sunat->getCdrZip()) {
                $comprobante->cdr_path = "sunat/cdr/R-{$nombre}.zip";
                Storage::disk('local')->put($comprobante->cdr_path, $sunat->getCdrZip());
            }
            $comprobante->estado = $cdr?->getAccepted() ? Comprobante::ACEPTADO : Comprobante::RECHAZADO;
            $comprobante->sunat_codigo = $cdr?->getCode();
            $notas = $cdr?->getNotes() ? ' Observaciones: '.implode(' | ', $cdr->getNotes()) : '';
            $comprobante->sunat_mensaje = trim(($cdr?->getDescription() ?? '').$notas);
        } else {
            $error = $sunat?->getError();
            $codigo = (int) ($error?->getCode() ?? 0);
            // 2000-3999: SUNAT lo rechazó (el número quedó usado). Lo demás
            // (excepciones 01xx-1999, sin conexión): no llegó, se reenvía.
            $comprobante->estado = $codigo >= 2000 && $codigo < 4000 ? Comprobante::RECHAZADO : Comprobante::ERROR;
            $comprobante->sunat_codigo = $error?->getCode() ? (string) $error->getCode() : null;
            $comprobante->sunat_mensaje = $error?->getMessage() ?: 'No se pudo comunicar con SUNAT.';
        }

        $comprobante->save();

        return $comprobante;
    }

    /**
     * Totales del comprobante desde las líneas del pedido (precios con IGV).
     * Sin descuento: la suma de las líneas. Con descuento: va global, sobre
     * la base, y el total queda igual al del pedido.
     *
     * @return array<string, mixed>
     */
    private function totales(Pedido $pedido): array
    {
        $tasa = 1 + config('app.igv');
        $detalles = [];
        $sumaValor = 0.0;

        foreach ($pedido->items as $item) {
            $conIgv = round((float) $item->subtotal, 2);
            $valor = round($conIgv / $tasa, 2);
            $igv = round($conIgv - $valor, 2);
            $sumaValor += $valor;

            $variante = $item->variante;
            $detalles[] = [
                'codProducto' => $variante?->sku ?: "V{$item->variante_id}",
                'unidad' => $variante?->unidad?->codigo_sunat ?: 'NIU',
                'cantidad' => (float) $item->cantidad,
                'descripcion' => mb_substr(trim(implode(' ', array_filter([
                    $variante?->producto?->nombre, $variante?->presentacion, $variante?->color?->nombre,
                ]))), 0, 250),
                'mtoValorUnitario' => round((float) $item->precio_unitario / $tasa, 6),
                'mtoPrecioUnitario' => round((float) $item->precio_unitario, 2),
                'mtoValorVenta' => $valor,
                'mtoBaseIgv' => $valor,
                'porcentajeIgv' => round(config('app.igv') * 100, 2),
                'igv' => $igv,
                'totalImpuestos' => $igv,
                'tipAfeIgv' => '10', // gravado - operación onerosa
            ];
        }

        $sumaValor = round($sumaValor, 2);
        $total = round((float) $pedido->total, 2);
        $descuento = round((float) $pedido->descuento, 2);
        $descuentoBase = $descuento > 0 ? round($descuento / $tasa, 2) : 0.0;
        $gravada = round($sumaValor - $descuentoBase, 2);

        return [
            'details' => $detalles,
            'sumaValor' => $sumaValor,
            'descuento' => $descuento,
            'descuentoBase' => $descuentoBase,
            'mtoOperGravadas' => $gravada,
            'mtoIGV' => round($total - $gravada, 2),
            'mtoImpVenta' => $total,
        ];
    }

    /**
     * @return array{tipoDoc: string, numDoc: string, rznSocial: string}
     */
    private function cliente(Pedido $pedido, string $tipo): array
    {
        $cliente = $pedido->cliente;

        if ($cliente && filled($cliente->numero_documento) && isset(self::TIPO_DOC_CLIENTE[$cliente->tipo_documento])) {
            return [
                'tipoDoc' => self::TIPO_DOC_CLIENTE[$cliente->tipo_documento],
                'numDoc' => $cliente->numero_documento,
                'rznSocial' => mb_substr($cliente->nombre, 0, 200),
            ];
        }

        // Boleta sin identificar (menos de S/ 700): "clientes varios".
        return ['tipoDoc' => '0', 'numDoc' => '-', 'rznSocial' => $cliente?->nombre ? mb_substr($cliente->nombre, 0, 200) : 'CLIENTES VARIOS'];
    }

    /**
     * @return array<string, mixed>
     */
    private function documento(Comprobante $comprobante, Pedido $pedido): array
    {
        $t = $this->totales($pedido);
        $cliente = $pedido->cliente;

        $datos = [
            'ublVersion' => '2.1',
            'tipoOperacion' => '0101', // venta interna
            'tipoDoc' => $comprobante->tipo,
            'serie' => $comprobante->serie,
            'correlativo' => (string) $comprobante->correlativo,
            // Con su zona: Greenter la pasa a hora de Perú al escribir el XML.
            // Sin zona la tomaba como UTC y restaba 5 horas de más.
            'fechaEmision' => $comprobante->fecha_emision->toIso8601String(),
            'formaPago' => ['tipo' => 'Contado'],
            'tipoMoneda' => 'PEN',
            'client' => [
                'tipoDoc' => $comprobante->cliente_tipo_doc,
                'numDoc' => $comprobante->cliente_num_doc,
                'rznSocial' => $comprobante->cliente_nombre,
                ...($cliente?->direccion ? ['address' => ['direccion' => $cliente->direccion]] : []),
            ],
            'mtoOperGravadas' => $t['mtoOperGravadas'],
            'mtoIGV' => $t['mtoIGV'],
            'totalImpuestos' => $t['mtoIGV'],
            'valorVenta' => $t['mtoOperGravadas'],
            'subTotal' => $t['mtoImpVenta'],
            'mtoImpVenta' => $t['mtoImpVenta'],
            'details' => $t['details'],
            'legends' => [['code' => '1000', 'value' => $comprobante->total_letras]],
        ];

        if ($t['descuentoBase'] > 0) {
            // Catálogo 53, código 02: descuento global que afecta la base imponible.
            $datos['descuentos'] = [[
                'codTipo' => '02',
                'montoBase' => $t['sumaValor'],
                'factor' => round($t['descuentoBase'] / max($t['sumaValor'], 0.01), 5),
                'monto' => $t['descuentoBase'],
            ]];
            $datos['mtoDescuentos'] = $t['descuentoBase'];
        }

        return $datos;
    }

    private function hashDe(?string $xml): ?string
    {
        if (! $xml || ! preg_match('/<ds:DigestValue>([^<]+)<\/ds:DigestValue>/', $xml, $m)) {
            return null;
        }

        return $m[1];
    }

    private function falla(string $mensaje): never
    {
        throw ValidationException::withMessages(['comprobante' => $mensaje]);
    }
}
