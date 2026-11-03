<?php

use Greenter\Ws\Services\SunatEndpoints;

/*
|--------------------------------------------------------------------------
| Facturación electrónica (SUNAT) con Greenter
|--------------------------------------------------------------------------
|
| Por defecto en BETA: los comprobantes van al ambiente de pruebas de SUNAT
| con el usuario MODDATOS y un certificado de prueba. Los datos de la
| empresa son los de su ficha RUC. Para producción: GREENTER_MODE=prod,
| el usuario SOL secundario (FACTURA1) y su clave, y el certificado digital (storage/app/certs/certificate.pem,
| que está en el volumen persistente y fuera de git).
|
*/

return [
    'mode' => env('GREENTER_MODE', 'beta'), // beta | prod

    'company' => [
        'ruc' => env('GREENTER_COMPANY_RUC', '20616230949'),
        'razonSocial' => env('GREENTER_COMPANY_NAME', 'NEXO TECHNOLOGY'),
        'nombreComercial' => env('GREENTER_COMPANY_COMMERCIAL_NAME', 'NEXO'),
        'address' => [
            'ubigeo' => env('GREENTER_COMPANY_UBIGEO', '211101'),
            'departamento' => env('GREENTER_COMPANY_DEPARTMENT', 'PUNO'),
            'provincia' => env('GREENTER_COMPANY_PROVINCE', 'SAN ROMAN'),
            'distrito' => env('GREENTER_COMPANY_DISTRICT', 'JULIACA'),
            'direccion' => env('GREENTER_COMPANY_ADDRESS', 'PJ. PASAJE CONFRATERNIDAD URB. CONCORDIA MZA. D LOTE. 4 A 2 CUADRAS DEL ESTADIO NUEVO'),
            // Código del establecimiento anexo en SUNAT (0000 = domicilio fiscal).
            'codLocal' => env('GREENTER_COMPANY_COD_LOCAL', '0000'),
        ],
        'certificate' => env('GREENTER_CERTIFICATE_PATH', storage_path('app/certs/certificate.pem')),
        'clave_sol' => [
            'user' => env('GREENTER_SOL_USER', 'MODDATOS'),
            'password' => env('GREENTER_SOL_PASS', 'MODDATOS'),
        ],
        // Sólo para guías de remisión (API nueva de SUNAT); no se usan aún.
        'credentials' => [
            'client_id' => env('GREENTER_CLIENT_ID', 'test-85e5b0ae-255c-4891-a595-0b98c65c9854'),
            'client_secret' => env('GREENTER_CLIENT_SECRET', 'test-Hty/M6QshYvPgItX2P0+Kw=='),
        ],
    ],

    'endpoints' => [
        'fe' => [
            'beta' => SunatEndpoints::FE_BETA,
            'prod' => SunatEndpoints::FE_PRODUCCION,
        ],
        'retencion' => [
            'beta' => SunatEndpoints::RETENCION_BETA,
            'prod' => SunatEndpoints::RETENCION_PRODUCCION,
        ],
        'api' => [
            'beta' => [
                'auth' => 'https://gre-test.nubefact.com/v1',
                'cpe' => 'https://gre-test.nubefact.com/v1',
            ],
            'prod' => [
                'auth' => 'https://api-seguridad.sunat.gob.pe/v1',
                'cpe' => 'https://api-cpe.sunat.gob.pe/v1',
            ],
        ],
    ],

    // El PDF de Greenter (wkhtmltopdf) no se usa: la representación impresa
    // es el ticket de 80 mm y el A4 del sistema. Queda la clave porque el
    // paquete la lee al iniciar.
    'report' => [
        'params' => [
            'system' => ['logo' => null, 'hash' => ''],
            'user' => ['header' => '', 'extras' => [], 'footer' => ''],
        ],
        'twigOptions' => ['strict_variables' => true],
        'templates' => resource_path('views/vendor/laravel-greenter'),
        'options' => ['no-outline'],
        'bin_path' => env('GREENTER_PDF_BIN_PATH'),
    ],
];
