<?php

namespace App\Models;

use App\ScrapingProvider;
use Database\Factories\ExtractionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Extraction extends Model
{
    /** @use HasFactory<ExtractionFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'provider', 'mode', 'url', 'options', 'status', 'remote_id', 'records',
        'provider_payload', 'error', 'poll_count', 'started_at', 'finished_at', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'provider' => ScrapingProvider::class, 'options' => 'array', 'records' => 'array',
            'provider_payload' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime',
            'poll_count' => 'integer', 'duration_ms' => 'integer',
        ];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'queued' => 'En cola', 'running' => 'En ejecución', 'waiting' => 'Esperando proveedor',
            'polling' => 'Consultando estado', 'completed' => 'Completado', 'failed' => 'Fallido',
            default => $this->status,
        };
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['queued', 'running', 'waiting', 'polling'], true);
    }

    public function columns(): array
    {
        $columns = [];
        foreach ($this->records ?? [] as $record) {
            $columns = array_unique([...$columns, ...array_keys($record)]);
        }

        return array_values($columns);
    }
}
