<?php

namespace Kamandlou\LaravelIcmp\Contracts;

use Kamandlou\LaravelIcmp\ValueObjects\PingResult;

interface Probe
{
    public function ping(string $host, ?int $count = null, ?float $timeout = null): PingResult;

    /**
     * @param list<string> $hosts
     * @return list<PingResult>
     */
    public function pingMany(array $hosts, ?int $count = null, ?float $timeout = null, ?int $concurrency = null): array;
}
