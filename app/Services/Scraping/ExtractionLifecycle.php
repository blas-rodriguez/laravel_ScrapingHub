<?php

namespace App\Services\Scraping;

use App\Jobs\PollExtraction;
use App\Models\Extraction;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ExtractionLifecycle
{
    public function apply(Extraction $extraction, ProviderResult $result): void
    {
        if (count($result->records) > 500 || strlen(json_encode($result->records, JSON_THROW_ON_ERROR)) > 2 * 1024 * 1024) {
            throw new RuntimeException('Los resultados superan los límites locales de 500 filas o 2 MB.');
        }
        DB::transaction(function () use ($extraction, $result): void {
            $extraction->update([
                'status' => $result->complete ? 'completed' : 'waiting',
                'records' => $result->complete ? SensitiveData::clean($result->records) : null,
                'provider_payload' => SensitiveData::clean($result->payload),
                'remote_id' => $result->remoteId ?? $extraction->remote_id,
                'finished_at' => $result->complete ? now() : null,
                'duration_ms' => $result->complete ? (int) $extraction->started_at->diffInMilliseconds(now()) : null,
                'error' => null,
            ]);
            if (! $result->complete) {
                if (blank($extraction->remote_id)) {
                    throw new RuntimeException('No se puede consultar una tarea sin identificador remoto.');
                }
                $this->schedulePoll($extraction);
            }
        });
    }

    public function schedulePoll(Extraction $extraction): void
    {
        $delay = $extraction->mode === 'demo' ? 1 : config('integrations.poll_delay');
        PollExtraction::dispatch($extraction->id)->delay(now()->addSeconds($delay))->afterCommit();
    }

    public function fail(int $id, ?Throwable $exception): void
    {
        $message = $exception instanceof RuntimeException
            ? $exception->getMessage() : 'La ejecución se interrumpió. Revisá el log local y el dashboard del proveedor.';
        Extraction::whereKey($id)->whereIn('status', ['queued', 'running', 'waiting', 'polling'])->update([
            'status' => 'failed', 'error' => mb_substr(SensitiveData::clean($message), 0, 2000), 'finished_at' => now(),
        ]);
    }
}
