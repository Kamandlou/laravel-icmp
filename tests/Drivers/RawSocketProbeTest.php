<?php

namespace Kamandlou\LaravelIcmp\Tests\Drivers;

use Kamandlou\LaravelIcmp\Drivers\RawSocketProbe;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class RawSocketProbeTest extends TestCase
{
    public function test_it_generates_a_valid_icmp_checksum(): void
    {
        $probe = new RawSocketProbe(['count' => 1, 'timeout' => 1.0]);
        $checksum = new ReflectionMethod($probe, 'checksum');
        $payload = 'Laravel ICMP';
        $withoutChecksum = pack('CCnnn', 8, 0, 0, 123, 1).$payload;
        $value = $checksum->invoke($probe, $withoutChecksum);
        $packet = pack('CCnnn', 8, 0, $value, 123, 1).$payload;

        $this->assertSame(0, $checksum->invoke($probe, $packet));
    }
}
