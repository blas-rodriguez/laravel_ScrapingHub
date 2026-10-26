<?php

namespace App\Services\Scraping;

use App\Models\Extraction;

interface ScrapingDriver
{
    public function start(Extraction $extraction): ProviderResult;

    public function poll(Extraction $extraction): ProviderResult;
}
