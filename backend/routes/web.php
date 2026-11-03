<?php

use App\Http\Controllers\ImpresionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Documentos impresos: sólo por enlace firmado y temporal, que entrega la API
// (POST api/ordenes-servicio/{orden}/impresion) a quien tiene permiso.
Route::get('imprimir/ordenes-servicio/{orden}/{formato}', [ImpresionController::class, 'ordenServicio'])
    ->middleware('signed')
    ->whereIn('formato', ImpresionController::FORMATOS)
    ->name('imprimir.orden-servicio');
