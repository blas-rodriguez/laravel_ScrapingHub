<?php

namespace App\Jobs;

use App\Models\Extraction;
use App\Services\Scraping\ExtractionLifecycle;
use App\Services\Scraping\ProviderRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PollExtraction implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 150;

    public bool $failOnTimeout = true;

    public function __construct(public int $extractionId) {}

    public function handle(ProviderRegistry $providers, ExtractionLifecycle $lifecycle): void
    {
        $claimed = Extraction::whereKey($this->extractionId)->where('status', 'waiting')
            ->update(['status' => 'polling', 'poll_count' => DB::raw('poll_count + 1')]);
        if (! $claimed) {
            return;
        }
        $extraction = Extraction::findOrFail($this->extractionId);
        try {
            if ($extraction->poll_count > config('integrations.max_polls') || $extraction->started_at->lt(now()->subMinutes(15))) {
                throw new RuntimeException('Se alcanzó el límite de espera local. La tarea remota puede seguir activa; consultá el dashboard del proveedor.');
            }
            $lifecycle->apply($extraction, $providers->for($extraction)->poll($extraction));
        } catch (Throwable $exception) {
            if (in_array($exception->getCode(), [429, 500, 502, 503, 504], true) && $extraction->poll_count < config('integrations.max_polls')) {
                $extraction->update(['status' => 'waiting']);
                $lifecycle->schedulePoll($extraction);

                return;
            }
            $lifecycle->fail($extraction->id, $exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(ExtractionLifecycle::class)->fail($this->extractionId, $exception);
    }
}
