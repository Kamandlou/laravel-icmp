<?php

namespace Kamandlou\LaravelIcmp\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Kamandlou\LaravelIcmp\ValueObjects\PingResult ping(string $host, ?int $count = null, ?float $timeout = null)
 * @method static list<\Kamandlou\LaravelIcmp\ValueObjects\PingResult> pingMany(array $hosts, ?int $count = null, ?float $timeout = null, ?int $concurrency = null)
 */
class Icmp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'icmp';
    }
}
