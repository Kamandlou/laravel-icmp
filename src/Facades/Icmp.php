<?php

namespace Kamandlou\LaravelIcmp\Facades;

use Illuminate\Support\Facades\Facade;

/** @method static \Kamandlou\LaravelIcmp\ValueObjects\PingResult ping(string $host, ?int $count = null, ?float $timeout = null) */
class Icmp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'icmp';
    }
}
