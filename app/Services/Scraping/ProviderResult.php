<?php

namespace App\Services\Scraping;

final readonly class ProviderResult
{
    /** @param array<int, array<string, mixed>> $records */
    public function __construct(
        public bool $complete,
        public array $records = [],
        public array $payload = [],
        public ?string $remoteId = null,
    ) {}
}
