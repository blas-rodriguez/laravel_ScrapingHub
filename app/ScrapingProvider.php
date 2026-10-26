<?php

namespace App;

enum ScrapingProvider: string
{
    case Firecrawl = 'firecrawl';
    case BrowseAi = 'browse-ai';
    case ScrapeGraphAi = 'scrapegraphai';

    public function label(): string
    {
        return match ($this) {
            self::Firecrawl => 'Firecrawl', self::BrowseAi => 'Browse AI', self::ScrapeGraphAi => 'ScrapeGraphAI',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Firecrawl => 'Convertí una página en Markdown o extraé JSON con instrucciones.',
            self::BrowseAi => 'Ejecutá un robot entrenado y recuperá sus tablas y textos.',
            self::ScrapeGraphAi => 'Describí lo que necesitás y obtené datos estructurados con IA.',
        };
    }

    public function configured(): bool
    {
        return filled(config('integrations.providers.'.$this->value.'.api_key'));
    }
}
