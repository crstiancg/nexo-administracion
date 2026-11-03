<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Consulta DNI (RENIEC) y RUC (SUNAT) en apis.net.pe para autocompletar un
 * cliente. Es una AYUDA: si no hay token, se agotó el cupo o la API no
 * responde, devuelve null y el cliente se carga a mano igual.
 *
 * Diferencias con la integración de muni-asis-sitra, a propósito:
 * - el token sale de la config (.env), nunca del código;
 * - se verifica el certificado TLS (allá `verify => false`);
 * - las respuestas se cachean: el mismo documento no gasta cupo dos veces.
 */
class ConsultaDocumento
{
    private const CACHE_DIAS = 30;

    public function habilitada(): bool
    {
        return filled(config('services.apis_net_pe.token'));
    }

    /**
     * @return array{nombre: string, direccion: ?string}|null
     */
    public function consultar(string $tipo, string $numero): ?array
    {
        if (! $this->habilitada() || ! in_array($tipo, [Cliente::DNI, Cliente::RUC], true)) {
            return null;
        }

        $clave = "consulta-documento:{$tipo}:{$numero}";
        if (($cacheado = Cache::get($clave)) !== null) {
            return $cacheado;
        }

        $datos = $tipo === Cliente::DNI ? $this->dni($numero) : $this->ruc($numero);

        // Sólo se cachea lo encontrado: un fallo de red no tiene que quedar
        // guardado como "no existe" durante un mes.
        if ($datos !== null) {
            Cache::put($clave, $datos, now()->addDays(self::CACHE_DIAS));
        }

        return $datos;
    }

    /**
     * @return array{nombre: string, direccion: ?string}|null
     */
    private function dni(string $numero): ?array
    {
        $r = $this->get('/v2/reniec/dni', $numero);
        if ($r === null) {
            return null;
        }

        $nombre = $r['nombreCompleto'] ?? trim(implode(' ', array_filter([
            $r['nombres'] ?? null, $r['apellidoPaterno'] ?? null, $r['apellidoMaterno'] ?? null,
        ])));

        return $nombre ? ['nombre' => $nombre, 'direccion' => null] : null;
    }

    /**
     * @return array{nombre: string, direccion: ?string}|null
     */
    private function ruc(string $numero): ?array
    {
        $r = $this->get('/v2/sunat/ruc', $numero);
        $nombre = $r['razonSocial'] ?? null;
        if (! $nombre) {
            return null;
        }

        $direccion = trim(implode(' - ', array_filter([
            $r['direccion'] ?? null, $r['distrito'] ?? null, $r['provincia'] ?? null, $r['departamento'] ?? null,
        ], fn ($parte) => filled($parte) && $parte !== '-')));

        return ['nombre' => $nombre, 'direccion' => $direccion ?: null];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function get(string $ruta, string $numero): ?array
    {
        try {
            $respuesta = Http::baseUrl(config('services.apis_net_pe.url'))
                ->withToken(config('services.apis_net_pe.token'))
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(8)
                ->get($ruta, ['numero' => $numero]);
        } catch (ConnectionException $e) {
            Log::warning('Consulta de documento: sin conexión con apis.net.pe', ['ruta' => $ruta, 'error' => $e->getMessage()]);

            return null;
        }

        // 404 = no existe; 401/429 = token inválido o cupo agotado. Nada de
        // eso tiene que frenar el alta del cliente.
        if (! $respuesta->successful()) {
            if ($respuesta->status() !== 404) {
                Log::warning('Consulta de documento: respuesta inesperada', ['ruta' => $ruta, 'status' => $respuesta->status()]);
            }

            return null;
        }

        return $respuesta->json();
    }
}
