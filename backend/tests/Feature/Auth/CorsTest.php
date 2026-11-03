<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_el_endpoint_de_token_acepta_preflight_desde_el_front(): void
    {
        $this->call('OPTIONS', '/oauth/token', server: [
            'HTTP_ORIGIN' => 'http://localhost:9000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ])->assertHeader('Access-Control-Allow-Origin');
    }
}
