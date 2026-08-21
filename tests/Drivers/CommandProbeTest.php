<?php

namespace Kamandlou\LaravelIcmp\Tests\Drivers;

use Kamandlou\LaravelIcmp\Drivers\CommandProbe;
use Kamandlou\LaravelIcmp\Exceptions\IcmpException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CommandProbeTest extends TestCase
{
    private CommandProbe $probe;

    protected function setUp(): void
    {
        $this->probe = new CommandProbe(['count' => 4, 'timeout' => 1.0, 'max_timeout' => 30.0]);
    }

    public function test_it_parses_unix_ping_output(): void
    {
        $result = $this->parse(<<<'OUTPUT'
PING cloudflare-dns.com (1.1.1.1): 56 data bytes
64 bytes from 1.1.1.1: icmp_seq=0 ttl=57 time=12.345 ms
64 bytes from 1.1.1.1: icmp_seq=1 ttl=57 time=13.456 ms

--- cloudflare-dns.com ping statistics ---
2 packets transmitted, 2 packets received, 0.0% packet loss
round-trip min/avg/max/stddev = 12.345/12.901/13.456/0.555 ms
OUTPUT, 2);

        $this->assertTrue($result->successful());
        $this->assertSame('1.1.1.1', $result->ip);
        $this->assertSame(2, $result->transmitted);
        $this->assertSame(2, $result->received);
        $this->assertSame(0.0, $result->packetLoss);
        $this->assertEqualsWithDelta(12.901, $result->avgMs, 0.0001);
        $this->assertSame(57, $result->replies[0]->ttl);
        $this->assertEqualsWithDelta(13.456, $result->replies[1]->timeMs, 0.0001);
    }

    public function test_it_parses_windows_ping_output(): void
    {
        $result = $this->parse(<<<'OUTPUT'

Pinging one.one.one.one [1.1.1.1] with 32 bytes of data:
Reply from 1.1.1.1: bytes=32 time=11ms TTL=57
Reply from 1.1.1.1: bytes=32 time<1ms TTL=57

Ping statistics for 1.1.1.1:
    Packets: Sent = 2, Received = 2, Lost = 0 (0% loss),
Approximate round trip times in milli-seconds:
    Minimum = 0ms, Maximum = 11ms, Average = 5ms
OUTPUT, 2);

        $this->assertTrue($result->successful());
        $this->assertSame(2, $result->received);
        $this->assertSame(32, $result->replies[0]->bytes);
        $this->assertSame(1.0, $result->replies[1]->timeMs);
        $this->assertSame(5.0, $result->avgMs);
        $this->assertSame(11.0, $result->maxMs);
    }

    public function test_it_returns_a_failed_structured_result_for_packet_loss(): void
    {
        $result = $this->parse("PING example.test (203.0.113.2): 56 data bytes\n\n--- example.test ping statistics ---\n1 packets transmitted, 0 packets received, 100.0% packet loss", 1, 2);

        $this->assertFalse($result->successful());
        $this->assertSame(100.0, $result->packetLoss);
        $this->assertNotNull($result->error);
        $this->assertSame([], $result->replies);
    }

    public function test_it_rejects_unsafe_or_out_of_range_options_before_running_a_command(): void
    {
        $this->expectException(IcmpException::class);
        $this->probe->ping("example.com\n--flood");
    }

    public function test_it_rejects_an_excessive_count(): void
    {
        $this->expectException(IcmpException::class);
        $this->probe->ping('example.com', 101);
    }

    public function test_it_rejects_an_excessive_timeout(): void
    {
        $this->expectException(IcmpException::class);
        $this->probe->ping('example.com', 1, 31.0);
    }

    public function test_it_rejects_an_invalid_batch_concurrency(): void
    {
        $this->expectException(IcmpException::class);
        $this->probe->pingMany(['example.com'], concurrency: 0);
    }

    private function parse(string $output, int $requestedCount, int $exitCode = 0): \Kamandlou\LaravelIcmp\ValueObjects\PingResult
    {
        $method = new ReflectionMethod($this->probe, 'parse');

        return $method->invoke($this->probe, 'example.test', $requestedCount, $output, $exitCode);
    }
}
