<?php

namespace App\Jobs;

use App\Models\Extraction;
use App\Services\Scraping\ExtractionLifecycle;
use App\Services\Scraping\ProviderRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExecuteExtraction implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 150;

    public bool $failOnTimeout = true;

    public function __construct(public int $extractionId) {}

    public function handle(ProviderRegistry $providers, ExtractionLifecycle $lifecycle): void
    {
        $claimed = Extraction::whereKey($this->extractionId)->where('status', 'queued')
            ->update(['status' => 'running', 'started_at' => now()]);
        if (! $claimed) {
            return;
        }
        $extraction = Extraction::findOrFail($this->extractionId);
        try {
            $lifecycle->apply($extraction, $providers->for($extraction)->start($extraction));
        } catch (Throwable $exception) {
            $lifecycle->fail($extraction->id, $exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(ExtractionLifecycle::class)->fail($this->extractionId, $exception);
    }
}
