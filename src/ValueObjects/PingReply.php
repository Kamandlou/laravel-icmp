<?php

namespace Kamandlou\LaravelIcmp\ValueObjects;

final readonly class PingReply
{
    public function __construct(
        public int $sequence,
        public ?float $timeMs,
        public ?int $ttl = null,
        public ?int $bytes = null,
        public ?string $from = null,
        public ?string $error = null,
    ) {
    }

    public function successful(): bool
    {
        return $this->timeMs !== null && $this->error === null;
    }

    public function toArray(): array
    {
        return [
            'sequence' => $this->sequence,
            'time_ms' => $this->timeMs,
            'ttl' => $this->ttl,
            'bytes' => $this->bytes,
            'from' => $this->from,
            'error' => $this->error,
        ];
    }
}
