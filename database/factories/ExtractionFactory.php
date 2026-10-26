<?php

namespace Database\Factories;

use App\Models\Extraction;
use App\ScrapingProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Extraction>
 */
class ExtractionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Extracción de ejemplo',
            'provider' => ScrapingProvider::Firecrawl,
            'mode' => 'demo',
            'url' => 'https://books.toscrape.com/',
            'options' => ['format' => 'markdown'],
            'status' => 'queued',
        ];
    }
}
