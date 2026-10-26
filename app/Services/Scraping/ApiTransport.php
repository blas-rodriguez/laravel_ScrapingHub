<?php

namespace App\Services\Scraping;

use App\ScrapingProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ApiTransport
{
    public function send(ScrapingProvider $provider, string $method, string $path, array $payload = []): array
    {
        $settings = config('integrations.providers.'.$provider->value);
        if (blank($settings['api_key'])) {
            throw new RuntimeException('Falta configurar la API key de '.$provider->label().' en .env.');
        }
        $request = Http::acceptJson()->asJson()->connectTimeout(10)->timeout(120)
            ->withOptions(['allow_redirects' => false]);
        $request = $provider === ScrapingProvider::ScrapeGraphAi
            ? $request->withHeaders(['SGAI-APIKEY' => $settings['api_key']])
            : $request->withToken($settings['api_key']);
        try {
            $response = $request->send($method, $settings['base_url'].$path, $method === 'GET' ? [] : ['json' => $payload]);
        } catch (ConnectionException) {
            throw new RuntimeException('No se pudo confirmar la respuesta del proveedor. Revisá su historial antes de volver a enviar una extracción.', 503);
        }
        if (! $response->successful()) {
            $reason = match ($response->status()) {
                401, 403 => 'Revisá la API key y los permisos de la cuenta.',
                402 => 'Revisá los créditos o el plan de la cuenta.',
                429 => 'Se alcanzó el límite de solicitudes del proveedor.',
                default => 'La API rechazó la solicitud o no está disponible.',
            };
            throw new RuntimeException($provider->label().' respondió HTTP '.$response->status().'. '.$reason, $response->status());
        }
        if (strlen($response->body()) > 2 * 1024 * 1024) {
            throw new RuntimeException('La respuesta supera el límite local de 2 MB.');
        }
        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('La API no devolvió un documento JSON válido.');
        }

        return SensitiveData::clean($data);
    }
}
