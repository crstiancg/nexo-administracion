<?php

namespace App\Models\Concerns;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra en `auditorias` quién creó, cambió o borró el modelo, con el
 * antes y el después de cada campo cambiado.
 *
 * Un modelo puede declarar `protected array $noAuditar = [...]` con los
 * campos que cambian solos y sólo harían ruido (el stock, un acumulado…).
 * Los cambios que siguen a un alta en la misma petición (el código, los
 * totales) no se registran aparte: son parte del alta.
 */
trait Auditable
{
    /** Nunca se registran (o se registran tapados). */
    private static array $siempreFuera = ['created_at', 'updated_at', 'remember_token'];

    private static array $tapados = ['password'];

    public static function bootAuditable(): void
    {
        static::created(fn (Model $modelo) => $modelo->auditar('creado'));
        static::updated(function (Model $modelo) {
            if (! $modelo->wasRecentlyCreated) {
                $modelo->auditar('editado');
            }
        });
        static::deleted(fn (Model $modelo) => $modelo->auditar('borrado'));
    }

    /** Cómo se lo nombra en la auditoría ("P-000012", "Laptop HP 250"). */
    public function etiquetaAuditoria(): ?string
    {
        return $this->codigo ?? $this->nombre ?? $this->name ?? $this->razon_social ?? $this->clave ?? null;
    }

    protected function auditar(string $evento): void
    {
        $cambios = null;
        if ($evento === 'editado') {
            $fuera = [...self::$siempreFuera, ...($this->noAuditar ?? [])];
            $cambios = [];
            foreach (array_diff_key($this->getChanges(), array_flip($fuera)) as $campo => $despues) {
                $antes = $this->getOriginal($campo);
                // "150" y "150.00" no son un cambio.
                if (is_numeric($antes) && is_numeric($despues) && (float) $antes === (float) $despues) {
                    continue;
                }
                $cambios[$campo] = in_array($campo, self::$tapados, true)
                    ? ['••••', '••••']
                    : [self::valor($antes), self::valor($despues)];
            }
            if (! $cambios) {
                return;
            }
        }

        Auditoria::query()->create([
            'user_id' => auth('api')->id() ?? auth()->id(),
            'evento' => $evento,
            'modelo' => class_basename($this),
            'modelo_id' => $this->getKey(),
            'etiqueta' => mb_substr((string) $this->etiquetaAuditoria(), 0, 150) ?: null,
            'cambios' => $cambios,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    private static function valor(mixed $valor): mixed
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }
        if (is_array($valor) || is_object($valor)) {
            return json_encode($valor, JSON_UNESCAPED_UNICODE);
        }

        return $valor;
    }
}
