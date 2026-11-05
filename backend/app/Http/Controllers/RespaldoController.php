<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Backups de la base y de los archivos subidos (spatie/laravel-backup): ver
 * los que hay en cada destino, hacer uno ahora y bajarlo. Se programan
 * todos los días en routes/console.php.
 */
class RespaldoController extends Controller
{
    /** Uno de más de esto ya no es "de hoy": algo falló con el programado. */
    public const HORAS_ALERTA = 26;

    public function index(): JsonResponse
    {
        $destinos = $this->destinos()->map(function (BackupDestination $destino) {
            $alcanzable = $destino->isReachable();
            $backups = $alcanzable ? $destino->backups() : collect();

            return [
                'disco' => $destino->diskName(),
                'en_la_nube' => $destino->diskName() !== 'local',
                'alcanzable' => $alcanzable,
                'error' => $alcanzable ? null : $destino->connectionError()?->getMessage(),
                'ocupado' => $alcanzable ? (int) $destino->usedStorage() : 0,
                'backups' => $backups->map(fn (Backup $b) => [
                    'archivo' => basename($b->path()),
                    'fecha' => $b->date()->toIso8601String(),
                    'tamano' => (int) $b->sizeInBytes(),
                ])->values(),
            ];
        })->values();

        $ultimo = $destinos->flatMap(fn ($d) => $d['backups'])->max('fecha');

        return response()->json([
            'destinos' => $destinos,
            'ultimo' => $ultimo,
            'al_dia' => $ultimo !== null && now()->diffInHours($ultimo, true) < self::HORAS_ALERTA,
        ]);
    }

    /** Hace un backup ahora (la base y los archivos). Tarda lo que pesen. */
    public function store(): JsonResponse
    {
        set_time_limit(600);

        $codigo = Artisan::call('backup:run', ['--no-interaction' => true]);
        if ($codigo !== 0) {
            // spatie lo escribe en la salida: "Backup failed because: …".
            $lineas = array_values(array_filter(array_map('trim', explode("\n", Artisan::output()))));
            $motivo = collect($lineas)->first(fn ($l) => str_contains(strtolower($l), 'failed')) ?? end($lineas) ?: 'error desconocido';
            report(new \RuntimeException('backup:run falló: '.implode(' | ', $lineas)));

            throw ValidationException::withMessages([
                'backup' => 'No se pudo hacer el backup: '.mb_substr($motivo, 0, 300),
            ]);
        }

        return $this->index();
    }

    /** Baja un backup de un destino (sólo los que están en la lista). */
    public function descargar(string $disco, string $archivo): StreamedResponse
    {
        $backup = $this->destinos()
            ->first(fn (BackupDestination $d) => $d->diskName() === $disco)
            ?->backups()
            ->first(fn (Backup $b) => basename($b->path()) === $archivo);

        abort_unless($backup, 404, 'Ese backup no existe.');

        return response()->streamDownload(function () use ($backup) {
            fpassthru($backup->stream());
        }, $archivo, ['Content-Type' => 'application/zip']);
    }

    /** @return \Illuminate\Support\Collection<int, BackupDestination> */
    private function destinos()
    {
        return collect(config('backup.backup.destination.disks'))
            ->map(fn (string $disco) => BackupDestination::create($disco, config('backup.backup.name')));
    }
}
