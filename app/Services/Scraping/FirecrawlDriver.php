<?php

namespace App\Services\Scraping;

use App\Models\Extraction;
use App\ScrapingProvider;
use RuntimeException;

class FirecrawlDriver implements ScrapingDriver
{
    public function __construct(private ApiTransport $api) {}

    public function start(Extraction $extraction): ProviderResult
    {
        $format = $extraction->options['format'] ?? 'markdown';
        $formats = ['markdown', 'links'];
        if ($format === 'json') {
            $json = ['type' => 'json', 'prompt' => $extraction->options['prompt']];
            if (! empty($extraction->options['schema'])) {
                $json['schema'] = $extraction->options['schema'];
            }
            $formats = [$json];
        }
        $payload = $this->api->send(ScrapingProvider::Firecrawl, 'POST', '/scrape', [
            'url' => $extraction->url, 'formats' => $formats, 'onlyMainContent' => true, 'timeout' => 60000,
        ]);
        if (($payload['success'] ?? false) !== true || ! is_array($payload['data'] ?? null)) {
            throw new RuntimeException('Firecrawl no confirmó una extracción exitosa. Revisá el destino en su dashboard.');
        }
        $data = $payload['data'];
        if ($format === 'json') {
            if (! array_key_exists('json', $data)) {
                throw new RuntimeException('Firecrawl no devolvió el JSON solicitado.');
            }
            $rows = ResultData::rows($data['json']);
        } else {
            if (! is_string($data['markdown'] ?? null)) {
                throw new RuntimeException('Firecrawl no devolvió el Markdown solicitado.');
            }
            $rows = [[
                'title' => $data['metadata']['title'] ?? '', 'url' => $extraction->url, 'markdown' => $data['markdown'],
            ]];
        }

        return new ProviderResult(true, $rows, $payload);
    }

    public function poll(Extraction $extraction): ProviderResult
    {
        throw new RuntimeException('El módulo Firecrawl utiliza el endpoint síncrono de scrape.');
    }
}
