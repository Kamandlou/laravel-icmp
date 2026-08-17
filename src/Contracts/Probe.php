<?php

namespace Kamandlou\LaravelIcmp\Contracts;

use Kamandlou\LaravelIcmp\ValueObjects\PingResult;

interface Probe
{
    public function ping(string $host, ?int $count = null, ?float $timeout = null): PingResult;
}
