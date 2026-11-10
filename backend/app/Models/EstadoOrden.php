<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Un estado de la orden de servicio (Catálogos > Estados de orden).
 *
 * El nombre, el ícono, el color y el orden son libres; lo que el sistema
 * hace con la orden lo decide el `tipo`:
 *
 * - inicial: donde nace toda orden. Uno solo, siempre activo.
 * - en_proceso: en el taller, sin terminar (atrasos, "sin técnico", en taller).
 * - listo: terminado; se entrega desde acá y avisa si no lo recogen.
 * - cerrado: quedó en el taller sin seguir (abandonado); se puede entregar
 *   si vuelve el cliente, pero no avisa.
 * - entregado: la entrega (cobra el saldo y saca los repuestos). Uno solo,
 *   siempre activo y final.
 *
 * La orden guarda la `clave`, que no cambia aunque se renombre el estado.
 */
#[Fillable(['nombre', 'tipo', 'icono', 'color', 'mensaje_cliente', 'notificar', 'activo'])]
class EstadoOrden extends Model
{
    public const INICIAL = 'inicial';

    public const EN_PROCESO = 'en_proceso';

    public const LISTO = 'listo';

    public const CERRADO = 'cerrado';

    public const ENTREGADO = 'entregado';

    public const TIPOS = [
        self::INICIAL => 'Inicial (al recibir el equipo)',
        self::EN_PROCESO => 'En proceso (en el taller)',
        self::LISTO => 'Listo para entregar',
        self::CERRADO => 'Cerrado sin entregar',
        self::ENTREGADO => 'Entregado',
    ];

    /** Uno de cada uno, fijos: no se crean, no se apagan, no cambian de tipo. */
    public const TIPOS_SISTEMA = [self::INICIAL, self::ENTREGADO];

    /** Los colores del chip (AppChip del front). */
    public const COLORES = ['info', 'warning', 'positive', 'negative'];

    private const CACHE = 'estados_orden';

    protected $table = 'estados_orden';

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'notificar' => 'boolean',
            'orden' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::olvidar());
        static::deleted(fn () => static::olvidar());
    }

    /** Tras cambiarlos sin pasar por el modelo (reordenar). */
    public static function olvidar(): void
    {
        Cache::store('array')->forget(self::CACHE);
    }

    public function esDeSistema(): bool
    {
        return in_array($this->tipo, self::TIPOS_SISTEMA, true);
    }

    /**
     * Todos (también los inactivos: hay órdenes que los tienen), en el orden
     * del flujo. Una consulta por request.
     *
     * @return Collection<int, EstadoOrden>
     */
    public static function todos(): Collection
    {
        return Cache::store('array')->rememberForever(self::CACHE, fn () => static::query()
            ->orderBy('orden')
            ->orderBy('id')
            ->get());
    }

    public static function deClave(?string $clave): ?self
    {
        return static::todos()->firstWhere('clave', $clave);
    }

    /** El nombre para mostrar (la clave si el estado ya no existe). */
    public static function nombreDe(?string $clave): string
    {
        return static::deClave($clave)?->nombre ?? (string) $clave;
    }

    /**
     * Las claves de los estados de esos tipos (activos o no: una orden
     * puede seguir en un estado que después se ocultó).
     *
     * @return list<string>
     */
    public static function clavesDe(string ...$tipos): array
    {
        return static::todos()->whereIn('tipo', $tipos)->pluck('clave')->values()->all();
    }

    /** Donde nace la orden. */
    public static function inicial(): string
    {
        return static::clavesDe(self::INICIAL)[0] ?? 'recibido';
    }

    /** La entrega. */
    public static function entregado(): string
    {
        return static::clavesDe(self::ENTREGADO)[0] ?? 'entregado';
    }

    /** En el taller, sin terminar. */
    public static function enTaller(): array
    {
        return static::clavesDe(self::INICIAL, self::EN_PROCESO);
    }

    /** Desde acá se entrega (con o sin reparar). */
    public static function entregables(): array
    {
        return static::clavesDe(self::LISTO, self::CERRADO);
    }

    /**
     * Lo que necesita el front (Configuración lo trae al iniciar sesión).
     *
     * @return list<array<string, mixed>>
     */
    public static function paraFront(): array
    {
        return static::todos()->map(fn (self $e) => $e->only([
            'id', 'clave', 'nombre', 'tipo', 'icono', 'color', 'mensaje_cliente', 'notificar', 'orden', 'activo',
        ]))->values()->all();
    }
}
