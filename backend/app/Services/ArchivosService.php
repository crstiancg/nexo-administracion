<?php

namespace App\Services;

use App\Models\Archivo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sincroniza los archivos polimórficos de un modelo (patrón de sistema-botica,
 * con tres diferencias a propósito):
 *
 * - Se guarda con nombre único ($file->store): con el nombre original, dos
 *   "foto.jpg" de modelos distintos se pisaban en disco.
 * - Los archivos físicos se borran recién DESPUÉS del commit: si la
 *   transacción falla, la fila vuelve y su archivo sigue ahí.
 * - Si la transacción falla, se borran los archivos recién subidos (si no,
 *   quedan huérfanos en disco).
 *
 * Las imágenes pasan por App\Services\Imagenes: miniatura WebP y medidas,
 * sin re-comprimir el original.
 *
 * Tiene que llamarse dentro de DB::transaction().
 */
class ArchivosService
{
    private const DISCO = 'public';

    public function __construct(private Imagenes $imagenes) {}

    /**
     * `$items` viene en el orden final: el índice pasa a ser el `orden`
     * (0 = portada). Con `id` se conserva uno existente, con `archivo` se sube
     * uno nuevo. Los existentes que no vienen se borran.
     *
     * Un `id` que no es de este modelo pero está en `$fuentes` se COPIA (así
     * una foto del balde de 4 gl se reusa en el de 1 gl). Las fuentes se
     * cargan antes de tocar nada: si la dueña original se borra en el mismo
     * guardado, su archivo físico sigue en disco hasta el commit.
     *
     * @param  array<int, array{id?: int|string|null, archivo?: UploadedFile|null}>|null  $items
     * @param  array<int, Archivo>  $fuentes  por id
     */
    public function sincronizar(Model $modelo, ?array $items, string $carpeta, array $fuentes = []): void
    {
        $existentes = $modelo->archivos()->get()->keyBy('id');
        $conservados = [];

        foreach (array_values($items ?? []) as $orden => $item) {
            $id = isset($item['id']) ? (int) $item['id'] : null;

            if ($id && $existentes->has($id)) {
                $existentes[$id]->update(['orden' => $orden]);
                $conservados[] = $id;
            } elseif ($id && isset($fuentes[$id])) {
                $this->copiar($modelo, $fuentes[$id], $carpeta, $orden);
            } elseif (($item['archivo'] ?? null) instanceof UploadedFile) {
                $this->subir($modelo, $item['archivo'], $carpeta, $orden);
            }
        }

        $this->eliminar($existentes->except($conservados)->all());
    }

    /**
     * Borra todos los archivos de un modelo (antes de borrarlo: el
     * polimórfico no tiene FK ni cascade).
     */
    public function eliminarDe(Model $modelo): void
    {
        $this->eliminar($modelo->archivos()->get()->all());
    }

    private function subir(Model $modelo, UploadedFile $archivo, string $carpeta, int $orden): Archivo
    {
        $ruta = $archivo->store($carpeta, self::DISCO);
        $mime = $archivo->getMimeType() ?? 'application/octet-stream';

        $imagen = str_starts_with($mime, 'image/') ? $this->imagenes->procesar(self::DISCO, $ruta) : null;

        // Si la transacción se revierte, el archivo no tiene fila que lo use.
        DB::afterRollBack(fn () => Storage::disk(self::DISCO)->delete(array_filter([$ruta, $imagen['miniatura'] ?? null])));

        return $modelo->archivos()->create([
            'nombre' => mb_substr($archivo->getClientOriginalName(), 0, 255),
            'ruta' => $ruta,
            'mime' => $mime,
            'tamano' => $archivo->getSize(),
            'orden' => $orden,
            ...($imagen ?? []),
        ]);
    }

    /**
     * Copia física, no una fila apuntando al mismo archivo: si no, borrar la
     * foto de una variante se la sacaría también a las otras.
     */
    private function copiar(Model $modelo, Archivo $fuente, string $carpeta, int $orden): Archivo
    {
        $disco = Storage::disk(self::DISCO);
        $nombre = Str::random(40);
        $extension = pathinfo($fuente->ruta, PATHINFO_EXTENSION);

        $ruta = $carpeta.'/'.$nombre.($extension ? ".{$extension}" : '');
        $disco->copy($fuente->ruta, $ruta);

        // La miniatura también se copia: regenerarla sería otra pasada de
        // compresión sobre lo mismo.
        $miniatura = null;
        if ($fuente->miniatura && $disco->exists($fuente->miniatura)) {
            $miniatura = $carpeta.'/miniaturas/'.$nombre.'.webp';
            $disco->copy($fuente->miniatura, $miniatura);
        }

        DB::afterRollBack(fn () => $disco->delete(array_filter([$ruta, $miniatura])));

        return $modelo->archivos()->create([
            ...$fuente->only(['nombre', 'mime', 'tamano', 'ancho', 'alto']),
            'ruta' => $ruta,
            'miniatura' => $miniatura,
            'orden' => $orden,
        ]);
    }

    /**
     * @param  Archivo[]  $archivos
     */
    private function eliminar(array $archivos): void
    {
        if ($archivos === []) {
            return;
        }

        $rutas = array_values(array_filter(array_merge(...array_map(fn (Archivo $a) => [$a->ruta, $a->miniatura], $archivos))));
        Archivo::query()->whereKey(array_map(fn (Archivo $a) => $a->getKey(), $archivos))->delete();

        DB::afterCommit(fn () => Storage::disk(self::DISCO)->delete($rutas));
    }
}
