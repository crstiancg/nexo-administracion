<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Backups diarios (Configuración > Backups). En el contenedor los corre el
// scheduler que arranca docker/entrypoint.sh (hora de Lima).
Schedule::command('backup:clean')->dailyAt('02:30')->timezone('America/Lima');
Schedule::command('backup:run')->dailyAt('03:00')->timezone('America/Lima')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
