<?php

namespace App\Services\Scraping;

use App\Models\Extraction;
use App\ScrapingProvider;

class DemoDriver implements ScrapingDriver
{
    public function start(Extraction $extraction): ProviderResult
    {
        if ($extraction->provider === ScrapingProvider::BrowseAi) {
            return new ProviderResult(false, payload: ['simulated' => true, 'status' => 'running'], remoteId: 'demo-task-'.$extraction->id);
        }

        return $this->completed($extraction);
    }

    public function poll(Extraction $extraction): ProviderResult
    {
        return $this->completed($extraction);
    }

    private function completed(Extraction $extraction): ProviderResult
    {
        $rows = [
            ['titulo' => 'Libro de demostración A', 'precio' => '12.90', 'moneda' => 'USD'],
            ['titulo' => 'Libro de demostración B', 'precio' => '18.50', 'moneda' => 'USD'],
            ['titulo' => 'Libro de demostración C', 'precio' => '24.00', 'moneda' => 'USD'],
        ];
        if ($extraction->provider === ScrapingProvider::Firecrawl && ($extraction->options['format'] ?? 'markdown') === 'markdown') {
            $rows = [['title' => 'Página de demostración', 'url' => $extraction->url, 'markdown' => "# Datos simulados\n\nEste contenido no fue extraído del sitio.\n\n- Libro A: USD 12.90\n- Libro B: USD 18.50"]];
        }

        return new ProviderResult(true, $rows, ['simulated' => true, 'provider' => $extraction->provider->value, 'note' => 'Datos locales de demostración. No se contactó a la API ni al sitio.', 'data' => $rows], $extraction->remote_id);
    }
}
