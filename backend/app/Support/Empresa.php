<?php

namespace App\Support;

use App\Models\Configuracion;
use App\Models\EstadoOrden;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Los datos de la empresa y los textos configurables: lo guardado desde
 * Configuración, y lo que falta con los valores de config/empresa.php.
 * Lo usan los documentos impresos, el seguimiento público y el front.
 */
final class Empresa
{
    /** Los textos que se editan en Configuración › Empresa y documentos. */
    public const CAMPOS = ['nombre', 'ruc', 'direccion', 'telefono', 'lema', 'garantia', 'pie_ticket', 'terminos_orden'];

    /** Mensajes de WhatsApp que no son de un estado de la orden. */
    public const OTROS_WHATSAPP = ['cotizacion', 'cuota_por_vencer', 'cuota_vencida'];

    /**
     * Mensajes de WhatsApp que se pueden cambiar: uno por estado de la orden
     * (los de Catálogos > Estados de orden), el que manda la proforma de una
     * cotización y los recordatorios de cuotas.
     *
     * @return list<string>
     */
    public static function clavesWhatsapp(): array
    {
        return [...EstadoOrden::todos()->pluck('clave')->all(), ...self::OTROS_WHATSAPP];
    }

    private const CACHE = 'empresa.configuracion';

    /**
     * @return array<string, mixed>
     */
    public static function datos(): array
    {
        $guardado = Cache::rememberForever(self::CACHE, fn () => Configuracion::query()->pluck('valor', 'clave')->all());

        $empresa = array_merge(
            array_intersect_key(config('empresa'), array_flip(self::CAMPOS)),
            array_filter((array) ($guardado['empresa'] ?? []), fn ($v) => $v !== null && $v !== ''),
        );

        $logo = $guardado['logo'] ?? null;

        return [
            ...$empresa,
            'logo_url' => $logo ? Storage::disk('public')->url($logo) : null,
            // Sólo los que se cambiaron; el front tiene los de por defecto.
            'whatsapp' => (object) array_filter((array) ($guardado['whatsapp'] ?? []), fn ($v) => is_string($v) && trim($v) !== ''),
        ];
    }

    public static function logoRuta(): ?string
    {
        return Configuracion::query()->where('clave', 'logo')->first()?->valor;
    }

    public static function guardar(string $clave, mixed $valor): void
    {
        Configuracion::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        Cache::forget(self::CACHE);
    }
}
