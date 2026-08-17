<?php

namespace Kamandlou\LaravelIcmp\Tests\ValueObjects;

use Kamandlou\LaravelIcmp\Tests\TestCase;
use Kamandlou\LaravelIcmp\ValueObjects\PingReply;
use Kamandlou\LaravelIcmp\ValueObjects\PingResult;

class PingResultTest extends TestCase
{
    public function test_it_serializes_a_structured_result(): void
    {
        $result = new PingResult('example.test', '203.0.113.1', 2, 1, 50.0, 1.2, 1.2, 1.2, null, [new PingReply(1, 1.2, 64, 56, '203.0.113.1')]);

        $this->assertTrue($result->successful());
        $this->assertSame(50.0, $result->toArray()['packet_loss']);
        $this->assertSame(1.2, $result->toArray()['replies'][0]['time_ms']);
    }
}
