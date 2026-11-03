<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Procesa las imágenes subidas SIN re-comprimir el original: el front ya lo
 * redimensionó y comprimió (Compressor.js) y otra pasada de JPEG/WebP sumaría
 * pérdida generacional. Acá sólo:
 *
 * - Se achica el original si llegó enorme (un cliente que no pasó por el
 *   front): única re-codificación, y a calidad alta.
 * - Se genera una miniatura WebP para listados y galerías.
 * - Se miden ancho y alto.
 */
class Imagenes
{
    public const LADO_MAXIMO = 2560;

    public const LADO_MINIATURA = 400;

    private const CALIDAD_ORIGINAL = 90;

    private const CALIDAD_MINIATURA = 80;

    private ImageManager $manager;

    public function __construct()
    {
        // GD viene con PHP y tiene WebP. Orienta según EXIF (fotos de celular
        // "acostadas") por defecto.
        $this->manager = ImageManager::gd();
    }

    /**
     * @return array{miniatura: string, ancho: int, alto: int, tamano: int}|null null si el archivo no se pudo leer como imagen
     */
    public function procesar(string $disco, string $ruta): ?array
    {
        $storage = Storage::disk($disco);

        try {
            $imagen = $this->manager->read($storage->path($ruta));

            if ($imagen->width() > self::LADO_MAXIMO || $imagen->height() > self::LADO_MAXIMO) {
                $imagen->scaleDown(self::LADO_MAXIMO, self::LADO_MAXIMO);
                $storage->put($ruta, (string) $imagen->encodeByPath($storage->path($ruta), quality: self::CALIDAD_ORIGINAL));
            }

            [$ancho, $alto] = [$imagen->width(), $imagen->height()];

            $miniatura = dirname($ruta).'/miniaturas/'.Str::beforeLast(basename($ruta), '.').'.webp';
            $storage->put($miniatura, (string) $imagen
                ->scaleDown(self::LADO_MINIATURA, self::LADO_MINIATURA)
                ->toWebp(quality: self::CALIDAD_MINIATURA));

            // El peso real: si se achicó el original, ya no es el que se subió.
            return ['miniatura' => $miniatura, 'ancho' => $ancho, 'alto' => $alto, 'tamano' => $storage->size($ruta)];
        } catch (Throwable $e) {
            // Una imagen que GD no puede abrir no tiene que tirar el guardado:
            // queda sin miniatura y el front usa el original.
            report($e);

            return null;
        }
    }
}
