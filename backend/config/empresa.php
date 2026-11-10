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

    // Textos de los documentos (se editan en Configuración).
    'lema' => 'Computadoras · Laptops · Impresoras · Suministros · Soporte técnico especializado · Consultoría y asesoramiento en proyectos tecnológicos',

    'garantia' => 'La garantía de equipos de cómputo en general se hace efectiva en nuestras oficinas, en los horarios de atención establecidos.',

    'pie_ticket' => '¡Gracias por su compra!',

    'terminos_orden' => 'El cliente declara entregar el equipo en el estado descrito. Los equipos no recogidos en 90 días desde el aviso de reparación podrán considerarse abandonados. Presente esta orden para recoger el equipo.',

    // Dónde vive la página pública de seguimiento (el front). El QR de cada
    // orden apunta a {frontend_url}/seguimiento/{token}.
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:9000'),

    // Cierre de caja (hora de la zona del negocio; se cambia en Configuración):
    // desde `desde` se avisa que hay que cerrarla y desde `hasta` el aviso es
    // urgente. A medianoche se cierra sola, sin arqueo.
    'caja_cierre_desde' => '19:00',

    'caja_cierre_hasta' => '22:00',

    // Minutos que vale el enlace firmado para abrir un documento impreso.
    'impresion_minutos' => 10,

];
