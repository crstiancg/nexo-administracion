<?php

namespace App\Models;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una boleta o factura electrónica de un pedido, con lo que respondió SUNAT.
 * No se edita: la emite y la reenvía App\Services\Facturacion. Un rechazo se
 * corrige emitiendo otra (con otro número).
 */
class Comprobante extends Model
{
    public const FACTURA = '01';

    public const BOLETA = '03';

    public const TIPOS = [
        self::BOLETA => 'Boleta de venta electrónica',
        self::FACTURA => 'Factura electrónica',
    ];

    /** Aún sin respuesta de SUNAT (se está enviando). */
    public const PENDIENTE = 'pendiente';

    public const ACEPTADO = 'aceptado';

    public const RECHAZADO = 'rechazado';

    /** No llegó a SUNAT (sin conexión, error del servicio): se reenvía. */
    public const ERROR = 'error';

    protected function casts(): array
    {
        return [
            'correlativo' => 'integer',
            'fecha_emision' => 'datetime',
            'op_gravada' => 'decimal:2',
            'igv' => 'decimal:2',
            'descuento' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** B001-00000012 */
    public function numero(): string
    {
        return $this->serie.'-'.str_pad((string) $this->correlativo, 8, '0', STR_PAD_LEFT);
    }

    /**
     * El texto del QR de la representación impresa, en el orden de SUNAT:
     * RUC | tipo | serie | número | IGV | total | fecha | tipo doc. y n° del
     * cliente | hash. null hasta que está firmado (sin hash no hay QR).
     */
    public function textoQr(): ?string
    {
        if (! $this->hash) {
            return null;
        }

        return implode('|', [
            config('greenter.company.ruc'),
            $this->tipo,
            $this->serie,
            $this->correlativo,
            number_format((float) $this->igv, 2, '.', ''),
            number_format((float) $this->total, 2, '.', ''),
            $this->fecha_emision?->format('Y-m-d'),
            $this->cliente_tipo_doc,
            $this->cliente_num_doc,
            $this->hash,
        ]).'|';
    }

    /** El QR como SVG (sin márgenes): el ticket lo dibuja tal cual. */
    public function qrSvg(): ?string
    {
        $texto = $this->textoQr();
        if ($texto === null) {
            return null;
        }

        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 0), new SvgImageBackEnd));

        // Sin la declaración <?xml: va incrustado en el HTML.
        return trim(preg_replace('/^<\?xml[^>]*>/', '', $writer->writeString($texto)));
    }

    /** Ya cuenta ante SUNAT (o puede contar): no se emite otro para el pedido. */
    public function vigente(): bool
    {
        return in_array($this->estado, [self::ACEPTADO, self::PENDIENTE, self::ERROR], true);
    }
}
