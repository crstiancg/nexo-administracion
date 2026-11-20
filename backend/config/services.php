<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Consulta de DNI (RENIEC) y RUC (SUNAT) al registrar clientes. El token
    // va SÓLO en el .env; sin token la consulta se desactiva y el cliente se
    // carga a mano.
    'apis_net_pe' => [
        'url' => env('APIS_NET_PE_URL', 'https://api.apis.net.pe'),
        'token' => env('APIS_NET_PE_TOKEN'),
    ],

    // API de Deltron (proveedor mayorista). Las credenciales van SÓLO en el
    // .env del servidor. Deltron sólo acepta llamadas desde las IPs
    // autorizadas en su panel (IPv4 del servidor de producción), así que se
    // llama siempre desde el backend, nunca desde el frontend.
    'deltron' => [
        'base_url' => env('DELTRON_BASE_URL'),
        'key' => env('DELTRON_API_KEY'),
        'secret' => env('DELTRON_API_SECRET'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
