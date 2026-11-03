<?php

namespace App\Support;

use App\Models\Configuracion;
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

    /** Estados de una orden con mensaje de WhatsApp propio. */
    public const ESTADOS_WHATSAPP = ['recibido', 'diagnostico', 'esperando_repuesto', 'en_reparacion', 'reparado', 'sin_reparacion', 'entregado', 'abandonado'];

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
