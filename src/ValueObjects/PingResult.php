<?php

namespace Kamandlou\LaravelIcmp\ValueObjects;

final readonly class PingResult
{
    /** @param list<PingReply> $replies */
    public function __construct(
        public string $host,
        public ?string $ip,
        public int $transmitted,
        public int $received,
        public float $packetLoss,
        public ?float $minMs,
        public ?float $avgMs,
        public ?float $maxMs,
        public ?float $stddevMs,
        public array $replies = [],
        public ?string $error = null,
        public ?string $rawOutput = null,
    ) {
    }

    public function successful(): bool
    {
        return $this->received > 0 && $this->error === null;
    }

    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'ip' => $this->ip,
            'transmitted' => $this->transmitted,
            'received' => $this->received,
            'packet_loss' => $this->packetLoss,
            'min_ms' => $this->minMs,
            'avg_ms' => $this->avgMs,
            'max_ms' => $this->maxMs,
            'stddev_ms' => $this->stddevMs,
            'successful' => $this->successful(),
            'replies' => array_map(fn (PingReply $reply) => $reply->toArray(), $this->replies),
            'error' => $this->error,
            'raw_output' => $this->rawOutput,
        ];
    }
}
