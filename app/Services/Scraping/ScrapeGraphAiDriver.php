<?php

namespace App\Services\Scraping;

use App\Models\Extraction;
use App\ScrapingProvider;
use RuntimeException;

class ScrapeGraphAiDriver implements ScrapingDriver
{
    public function __construct(private ApiTransport $api) {}

    public function start(Extraction $extraction): ProviderResult
    {
        $body = ['url' => $extraction->url, 'prompt' => $extraction->options['prompt']];
        if (! empty($extraction->options['schema'])) {
            $body['schema'] = $extraction->options['schema'];
        }
        $payload = $this->api->send(ScrapingProvider::ScrapeGraphAi, 'POST', '/extract', $body);
        if (! array_key_exists('json', $payload) || $payload['json'] === null) {
            throw new RuntimeException('ScrapeGraphAI v2 no devolvió datos en el campo json.');
        }

        return new ProviderResult(true, ResultData::rows($payload['json']), $payload, isset($payload['id']) ? ResultData::opaqueId($payload['id']) : null);
    }

    public function poll(Extraction $extraction): ProviderResult
    {
        throw new RuntimeException('El módulo ScrapeGraphAI utiliza el endpoint síncrono v2 de extract.');
    }
}
