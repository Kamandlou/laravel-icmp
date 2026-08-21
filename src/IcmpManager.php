<?php

namespace Kamandlou\LaravelIcmp;

use Kamandlou\LaravelIcmp\Contracts\Probe;
use Kamandlou\LaravelIcmp\Drivers\CommandProbe;
use Kamandlou\LaravelIcmp\Drivers\RawSocketProbe;
use Kamandlou\LaravelIcmp\Exceptions\IcmpException;
use Kamandlou\LaravelIcmp\ValueObjects\PingResult;

class IcmpManager
{
    public function __construct(private readonly array $config)
    {
    }

    public function ping(string $host, ?int $count = null, ?float $timeout = null): PingResult
    {
        return $this->driver()->ping($host, $count, $timeout);
    }

    /**
     * @param list<string> $hosts
     * @return list<PingResult>
     */
    public function pingMany(array $hosts, ?int $count = null, ?float $timeout = null, ?int $concurrency = null): array
    {
        return $this->driver()->pingMany($hosts, $count, $timeout, $concurrency);
    }

    public function driver(?string $name = null): Probe
    {
        return match ($name ?? $this->config['driver']) {
            'command' => new CommandProbe($this->config),
            'raw' => new RawSocketProbe($this->config),
            default => throw new IcmpException("Unsupported ICMP driver [".($name ?? $this->config['driver']).'].'),
        };
    }
}
