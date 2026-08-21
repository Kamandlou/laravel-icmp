<?php

namespace Kamandlou\LaravelIcmp\Drivers;

use Kamandlou\LaravelIcmp\Contracts\Probe;
use Kamandlou\LaravelIcmp\Exceptions\IcmpException;
use Kamandlou\LaravelIcmp\ValueObjects\PingReply;
use Kamandlou\LaravelIcmp\ValueObjects\PingResult;

/** Native IPv4 echo probe. Requires ext-sockets and raw-socket privileges. */
final class RawSocketProbe implements Probe
{
    public function __construct(private readonly array $config) {}

    public function ping(string $host, ?int $count = null, ?float $timeout = null): PingResult
    {
        if (! extension_loaded('sockets')) throw new IcmpException('The raw driver requires PHP ext-sockets.');
        $host = trim($host);
        $ip = gethostbyname($host);
        if ($host === '' || $ip === $host && filter_var($ip, FILTER_VALIDATE_IP) === false) throw new IcmpException("Unable to resolve host [{$host}].");
        $count = $count ?? (int) $this->config['count'];
        $timeout = $timeout ?? (float) $this->config['timeout'];
        if ($count < 1 || $count > 100 || $timeout <= 0 || $timeout > (float) ($this->config['max_timeout'] ?? 30)) throw new IcmpException('Invalid count or timeout.');

        $socket = @socket_create(AF_INET, SOCK_RAW, 1);
        if ($socket === false) throw new IcmpException('Cannot open raw ICMP socket. Grant CAP_NET_RAW/root access or use the command driver.');
        $id = random_int(0, 65535); $replies = [];
        try {
            for ($sequence = 1; $sequence <= $count; $sequence++) {
                $payload = 'LICMP'.pack('d', microtime(true));
                $packet = pack('CCnnn', 8, 0, 0, $id, $sequence).$payload;
                $packet = pack('CCnnn', 8, 0, $this->checksum($packet), $id, $sequence).$payload;
                $started = hrtime(true);
                socket_sendto($socket, $packet, strlen($packet), 0, $ip, 0);
                $deadline = microtime(true) + $timeout;
                $matched = false;
                while (microtime(true) < $deadline) {
                    $remaining = $deadline - microtime(true);
                    $read = [$socket]; $write = null; $except = null;
                    $ready = socket_select($read, $write, $except, (int) $remaining, (int) (($remaining - floor($remaining)) * 1_000_000));
                    if ($ready === false || $ready === 0) break;
                    $from = ''; $port = 0;
                    if (@socket_recvfrom($socket, $reply, 65535, 0, $from, $port) === false || $reply === '') continue;
                    $ihl = (ord($reply[0]) & 0x0f) * 4;
                    if (strlen($reply) < $ihl + 8) continue;
                    $icmp = unpack('Ctype/Ccode/nchecksum/nid/nsequence', substr($reply, $ihl, 8));
                    if ($icmp['type'] !== 0 || $icmp['id'] !== $id || $icmp['sequence'] !== $sequence) continue;
                    $replies[] = new PingReply($sequence, (hrtime(true) - $started) / 1_000_000, null, strlen($reply) - $ihl, $from);
                    $matched = true;
                    break;
                }
                if (! $matched) $replies[] = new PingReply($sequence, null, error: 'Request timed out.');
            }
        } finally { socket_close($socket); }
        $times = array_values(array_filter(array_map(fn (PingReply $r) => $r->timeMs, $replies), fn ($v) => $v !== null));
        $received = count($times); $avg = $received ? array_sum($times) / $received : null;
        return new PingResult($host, $ip, $count, $received, 100 * ($count - $received) / $count, $times ? min($times) : null, $avg, $times ? max($times) : null, null, $replies, $received ? null : 'No ICMP echo replies received.');
    }

    /**
     * Raw sockets share one receive queue, so probes are deliberately run in order.
     * @param list<string> $hosts
     * @return list<PingResult>
     */
    public function pingMany(array $hosts, ?int $count = null, ?float $timeout = null, ?int $concurrency = null): array
    {
        $concurrency = $concurrency ?? (int) ($this->config['concurrency'] ?? 5);
        if ($concurrency < 1 || $concurrency > 100) {
            throw new IcmpException('Concurrency must be between 1 and 100.');
        }

        return array_map(fn (string $host) => $this->ping($host, $count, $timeout), $hosts);
    }

    private function checksum(string $data): int
    {
        $sum = 0; foreach (unpack('n*', str_pad($data, strlen($data) + (strlen($data) % 2), "\0")) as $word) $sum += $word;
        $sum = ($sum >> 16) + ($sum & 0xffff); $sum += $sum >> 16;
        return ~$sum & 0xffff;
    }
}
