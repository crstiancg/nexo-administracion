<?php

namespace App\Services;

use Anthropic\Client;
use App\Http\Resources\ClienteResource;
use App\Http\Resources\VarianteStockResource;
use App\Models\Cliente;
use App\Models\User;
use App\Models\Variante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Asistente de cotización: el vendedor escribe lo que pide el cliente en
 * lenguaje natural ("PC core i5 con 16 GB y SSD de 500 para Juan Pérez,
 * garantía 1 año") y Claude arma un BORRADOR buscando en los clientes y el
 * catálogo de la sede. No guarda nada: el borrador se carga en el form de la
 * cotización y el vendedor lo revisa antes de guardar.
 *
 * Bucle manual de tool use: buscar_clientes / buscar_productos se ejecutan
 * acá contra la base; proponer_cotizacion (strict) cierra el bucle con el
 * borrador.
 */
class AsistenteCotizacion
{
    public const MODELO = 'claude-opus-5-5';

    /** Vueltas máximas de herramientas: corta un bucle que no converge. */
    private const MAX_VUELTAS = 8;

    private const COMPONENTES_PC = [
        'Procesador', 'Placa madre', 'Tarjeta de video', 'Memoria RAM', 'Disco sólido SSD',
        'Disco mecánico HDD', 'Case', 'Fuente de poder', 'Monitor', 'Kit completo T/M/PM/A',
        'Teclado', 'Mouse', 'Parlante', 'Auricular', 'Estabilizador',
    ];

    private ?Client $client = null;

    /**
     * @return array{cliente: ?array, cotizacion: array<string, mixed>, notas: string}
     */
    public function borrador(string $pedido, User $usuario): array
    {
        $sedeId = $usuario->sedeOperativa();
        $messages = [['role' => 'user', 'content' => $pedido]];

        for ($vuelta = 0; $vuelta < self::MAX_VUELTAS; $vuelta++) {
            $respuesta = $this->llamar($messages);

            if ($respuesta->stopReason === 'refusal') {
                throw ValidationException::withMessages(['texto' => 'El asistente no pudo procesar ese pedido. Escríbelo de otra forma.']);
            }

            $resultados = [];
            foreach ($respuesta->content as $bloque) {
                if ($bloque->type !== 'tool_use') {
                    continue;
                }
                if ($bloque->name === 'proponer_cotizacion') {
                    return $this->armar((array) $bloque->input, $sedeId);
                }
                $resultados[] = [
                    'type' => 'tool_result',
                    'toolUseID' => $bloque->id,
                    'content' => json_encode($this->ejecutar($bloque->name, (array) $bloque->input, $sedeId), JSON_UNESCAPED_UNICODE),
                ];
            }

            if ($resultados === []) {
                // Respondió con texto sin proponer (le falta un dato): se lo
                // mostramos al vendedor.
                $texto = collect($respuesta->content)->where('type', 'text')->pluck('text')->implode("\n");
                throw ValidationException::withMessages(['texto' => $texto ?: 'El asistente no pudo armar la cotización. Agrega más detalle.']);
            }

            $messages[] = ['role' => 'assistant', 'content' => $respuesta->content];
            $messages[] = ['role' => 'user', 'content' => $resultados];
        }

        throw ValidationException::withMessages(['texto' => 'El asistente tardó demasiado. Intenta con un pedido más concreto.']);
    }

    /**
     * Una vuelta con Claude. Protegido: los tests lo reemplazan para no
     * llamar a la API.
     *
     * @param  array<int, array<string, mixed>>  $messages
     */
    protected function llamar(array $messages): object
    {
        if (! config('services.anthropic.key')) {
            throw ValidationException::withMessages([
                'texto' => 'El asistente no está configurado: falta ANTHROPIC_API_KEY en el .env del servidor.',
            ]);
        }

        $this->client ??= new Client(apiKey: config('services.anthropic.key'));

        // Beta por `fallbacks`: si el modelo declina por política, el
        // servidor reintenta con el modelo de respaldo que corresponda.
        return $this->client->beta->messages->create(
            model: self::MODELO,
            maxTokens: 16000,
            outputConfig: ['effort' => 'medium'],
            system: [['type' => 'text', 'text' => $this->instrucciones(), 'cacheControl' => ['type' => 'ephemeral']]],
            tools: $this->herramientas(),
            messages: $messages,
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );
    }

    private function instrucciones(): string
    {
        $componentes = implode(', ', self::COMPONENTES_PC);

        return <<<TXT
        Eres el asistente de cotizaciones de Nexo Technology, una tienda de computadoras, laptops, impresoras,
        suministros y soporte técnico en Puno, Perú. Precios en soles (S/), con IGV incluido.

        El vendedor te escribe lo que pide un cliente. Arma un borrador de cotización:
        1. Si menciona un cliente (nombre, DNI o RUC), búscalo con buscar_clientes. Si no aparece, deja cliente_id en null
           y menciónalo en las notas.
        2. Busca cada producto con buscar_productos usando términos cortos ("ssd 500", "core i5", "ram 16"). Prefiere lo
           que tiene stock. Usa el precio del catálogo salvo que el vendedor indique otro.
        3. Si algo no está en el catálogo, agrégalo como ítem libre (sin variante_id, con una descripción clara) y precio 0
           si no conoces el precio; avisa en las notas que hay que ponerle precio.
        4. Si pide una computadora armada (PC de escritorio por piezas), usa tipo "pc" y pon en cada ítem el componente:
           {$componentes}. En "pc" no inventes piezas que no pidió.
        5. Garantía, forma de pago y entrega sólo si el vendedor las menciona; si no, null.
        6. Termina SIEMPRE llamando a proponer_cotizacion una sola vez. En notas, explica en una o dos frases en
           español sencillo lo que elegiste y lo que falta revisar.
        No inventes ids: usa sólo los que devuelven las herramientas.
        TXT;
    }

    /** @return array<int, array<string, mixed>> */
    private function herramientas(): array
    {
        return [
            [
                'name' => 'buscar_clientes',
                'description' => 'Busca clientes por nombre, DNI/RUC o teléfono. Devuelve hasta 5 coincidencias con su id.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['texto' => ['type' => 'string', 'description' => 'Nombre, documento o teléfono']],
                    'required' => ['texto'],
                ],
            ],
            [
                'name' => 'buscar_productos',
                'description' => 'Busca en el catálogo de la sede por nombre o SKU. Devuelve hasta 8 presentaciones con id, precio (S/) y stock.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['texto' => ['type' => 'string', 'description' => 'Términos cortos: "ssd 500", "mouse logitech"']],
                    'required' => ['texto'],
                ],
            ],
            [
                'name' => 'proponer_cotizacion',
                'description' => 'Entrega el borrador final de la cotización. Llámala una sola vez, al terminar.',
                'strict' => true,
                'inputSchema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['cliente_id', 'tipo', 'garantia', 'forma_pago', 'entrega', 'items', 'notas'],
                    'properties' => [
                        'cliente_id' => ['type' => ['integer', 'null']],
                        'tipo' => ['type' => 'string', 'enum' => ['normal', 'pc']],
                        'garantia' => ['type' => ['string', 'null'], 'description' => 'Ej. "1 año", "6 meses"'],
                        'forma_pago' => ['type' => ['string', 'null']],
                        'entrega' => ['type' => ['string', 'null']],
                        'notas' => ['type' => 'string', 'description' => 'Para el vendedor: qué elegiste y qué revisar'],
                        'items' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['variante_id', 'descripcion', 'componente', 'cantidad', 'precio_unitario'],
                                'properties' => [
                                    'variante_id' => ['type' => ['integer', 'null'], 'description' => 'null si no está en el catálogo'],
                                    'descripcion' => ['type' => ['string', 'null'], 'description' => 'Sólo para ítems sin variante_id'],
                                    'componente' => ['type' => ['string', 'null'], 'description' => 'Sólo en tipo "pc"'],
                                    'cantidad' => ['type' => 'number'],
                                    'precio_unitario' => ['type' => 'number'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ejecutar(string $herramienta, array $input, ?int $sedeId): array
    {
        $texto = trim((string) ($input['texto'] ?? ''));

        return match ($herramienta) {
            'buscar_clientes' => Cliente::query()
                ->where(fn (Builder $q) => $q
                    ->where('nombre', 'like', "%{$texto}%")
                    ->orWhere('numero_documento', 'like', "%{$texto}%")
                    ->orWhere('telefono', 'like', "%{$texto}%"))
                ->limit(5)
                ->get(['id', 'nombre', 'tipo_documento', 'numero_documento'])
                ->toArray(),
            'buscar_productos' => $this->variantes($sedeId)
                ->where(function (Builder $q) use ($texto) {
                    // Cada palabra tiene que aparecer en el nombre o el SKU.
                    foreach (preg_split('/\s+/', $texto) ?: [] as $palabra) {
                        $q->where(fn (Builder $w) => $w
                            ->where('variantes.sku', 'like', "%{$palabra}%")
                            ->orWhere('variantes.presentacion', 'like', "%{$palabra}%")
                            ->orWhereHas('producto', fn (Builder $p) => $p->where('nombre', 'like', "%{$palabra}%")));
                    }
                })
                ->limit(8)
                ->get()
                ->map(function (Variante $v) {
                    $datos = (new VarianteStockResource($v))->resolve(request());

                    return [
                        'id' => $v->id,
                        'producto' => $v->producto?->nombre,
                        'presentacion' => trim($v->presentacion.' '.($v->color?->nombre ?? '')),
                        'sku' => $v->sku,
                        'precio' => $datos['precio'] ?? null,
                        'stock' => $datos['stock'] ?? null,
                    ];
                })
                ->all(),
            default => [['error' => "Herramienta desconocida: {$herramienta}"]],
        };
    }

    private function variantes(?int $sedeId): Builder
    {
        return Variante::query()
            ->when($sedeId, fn (Builder $q) => $q->habilitadaEnSede($sedeId)->conStockDeSede($sedeId))
            ->with(['producto:id,nombre,precio,categoria_id,marca_id,maneja_lotes', 'producto.portada', 'portada', 'unidad:id,nombre,abreviatura,fraccionable', 'color:id,nombre,hexadecimal'])
            ->whereHas('producto', fn (Builder $p) => $p->where('activo', true));
    }

    /**
     * El borrador listo para el form: ids que existen de verdad (lo que el
     * modelo inventó se descarta) y las variantes completas para mostrarlas.
     *
     * @return array{cliente: ?array, cotizacion: array<string, mixed>, notas: string}
     */
    private function armar(array $propuesta, ?int $sedeId): array
    {
        $tipo = ($propuesta['tipo'] ?? 'normal') === 'pc' ? 'pc' : 'normal';
        $ids = collect($propuesta['items'] ?? [])->pluck('variante_id')->filter()->unique()->all();
        $variantes = $this->variantes($sedeId)->whereIn('variantes.id', $ids)->get()->keyBy('id');

        $items = [];
        foreach ($propuesta['items'] ?? [] as $item) {
            $variante = $variantes->get((int) ($item['variante_id'] ?? 0));
            $descripcion = trim((string) ($item['descripcion'] ?? ''));
            if (! $variante && $descripcion === '') {
                continue;
            }
            $items[] = [
                'variante_id' => $variante?->id,
                'variante' => $variante ? (new VarianteStockResource($variante))->resolve(request()) : null,
                'descripcion' => $variante ? null : $descripcion,
                'unidad' => $variante ? null : 'und',
                'componente' => $tipo === 'pc' ? ($item['componente'] ?? null) : null,
                'cantidad' => max(0.001, (float) ($item['cantidad'] ?? 1)),
                'precio_unitario' => number_format(max(0, (float) ($item['precio_unitario'] ?? 0)), 2, '.', ''),
            ];
        }

        $cliente = ! empty($propuesta['cliente_id']) ? Cliente::find($propuesta['cliente_id']) : null;

        return [
            'cliente' => $cliente ? (new ClienteResource($cliente))->resolve(request()) : null,
            'cotizacion' => [
                'tipo' => $tipo,
                'garantia' => $propuesta['garantia'] ?? null,
                'forma_pago' => $propuesta['forma_pago'] ?? null,
                'entrega' => $propuesta['entrega'] ?? null,
                'items' => $items,
            ],
            'notas' => (string) ($propuesta['notas'] ?? ''),
        ];
    }
}
