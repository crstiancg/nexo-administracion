<?php

namespace App\Console\Commands;

use App\Models\Archivo;
use App\Services\Imagenes;
use Illuminate\Console\Command;

/**
 * Genera la miniatura (y mide ancho y alto) de las imágenes que no la tienen:
 * las subidas antes de que existieran las miniaturas, o las que fallaron al
 * procesarse. Se puede correr las veces que haga falta.
 */
class GenerarMiniaturas extends Command
{
    protected $signature = 'archivos:miniaturas';

    protected $description = 'Genera las miniaturas WebP de las imágenes que no la tienen';

    public function handle(Imagenes $imagenes): int
    {
        $pendientes = Archivo::query()->whereNull('miniatura')->where('mime', 'like', 'image/%');

        $total = $pendientes->count();
        if ($total === 0) {
            $this->info('Todas las imágenes ya tienen miniatura.');

            return self::SUCCESS;
        }

        $fallidas = 0;
        $this->withProgressBar($pendientes->lazyById(), function (Archivo $archivo) use ($imagenes, &$fallidas) {
            $datos = $imagenes->procesar('public', $archivo->ruta);

            $datos ? $archivo->update($datos) : $fallidas++;
        });

        $this->newLine();
        $this->info('Procesadas: '.($total - $fallidas).($fallidas ? ", con error: {$fallidas} (ver el log)" : '').'.');

        return $fallidas ? self::FAILURE : self::SUCCESS;
    }
}
