<?php

/*
|--------------------------------------------------------------------------
| Datos de la empresa para los documentos impresos
|--------------------------------------------------------------------------
|
| Encabezan la orden de servicio y la etiqueta (resources/views/impresion).
| Los mismos que usa el front (src/config/tienda.js).
|
*/

return [

    'nombre' => env('EMPRESA_NOMBRE', 'NEXO TECHNOLOGY S.R.L.'),

    'ruc' => env('EMPRESA_RUC', '20610596780'),

    'direccion' => env('EMPRESA_DIRECCION', 'Jr. Arequipa 418 - Puno'),

    'telefono' => env('EMPRESA_TELEFONO', '900 551 900 - 900 180 000'),

    // Dónde vive la página pública de seguimiento (el front). El QR de cada
    // orden apunta a {frontend_url}/seguimiento/{token}.
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:9000'),

    // Minutos que vale el enlace firmado para abrir un documento impreso.
    'impresion_minutos' => 10,

];
