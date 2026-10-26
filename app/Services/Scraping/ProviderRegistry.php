<?php

namespace App\Services\Scraping;

use App\Models\Extraction;
use App\ScrapingProvider;

class ProviderRegistry
{
    public function for(Extraction $extraction): ScrapingDriver
    {
        if ($extraction->mode === 'demo') {
            return app(DemoDriver::class);
        }

        return app(match ($extraction->provider) {
            ScrapingProvider::Firecrawl => FirecrawlDriver::class,
            ScrapingProvider::BrowseAi => BrowseAiDriver::class,
            ScrapingProvider::ScrapeGraphAi => ScrapeGraphAiDriver::class,
        });
    }
}
