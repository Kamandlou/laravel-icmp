<?php

namespace Kamandlou\LaravelIcmp\Tests;

use Kamandlou\LaravelIcmp\Drivers\CommandProbe;
use Kamandlou\LaravelIcmp\Drivers\RawSocketProbe;
use Kamandlou\LaravelIcmp\Exceptions\IcmpException;
use Kamandlou\LaravelIcmp\Facades\Icmp;
use Kamandlou\LaravelIcmp\IcmpManager;

class IcmpManagerTest extends TestCase
{
    public function test_it_registers_the_manager_and_facade(): void
    {
        $this->assertInstanceOf(IcmpManager::class, $this->app->make('icmp'));
        $this->assertInstanceOf(IcmpManager::class, Icmp::getFacadeRoot());
    }

    public function test_it_resolves_each_supported_driver(): void
    {
        $manager = new IcmpManager(['driver' => 'command', 'count' => 1, 'timeout' => 1.0]);

        $this->assertInstanceOf(CommandProbe::class, $manager->driver());
        $this->assertInstanceOf(RawSocketProbe::class, $manager->driver('raw'));
    }

    public function test_it_rejects_an_unknown_driver(): void
    {
        $this->expectException(IcmpException::class);
        (new IcmpManager(['driver' => 'invalid']))->driver();
    }

    public function test_it_delegates_multiple_hosts_to_the_selected_driver(): void
    {
        $manager = new IcmpManager(['driver' => 'command', 'count' => 1, 'timeout' => 1.0, 'max_timeout' => 30.0]);

        $results = $manager->pingMany([], concurrency: 1);

        $this->assertSame([], $results);
    }
}
