<?php

namespace Kamandlou\LaravelIcmp\Drivers;

use Kamandlou\LaravelIcmp\Contracts\Probe;
use Kamandlou\LaravelIcmp\Exceptions\IcmpException;
use Kamandlou\LaravelIcmp\ValueObjects\PingReply;
use Kamandlou\LaravelIcmp\ValueObjects\PingResult;

final class CommandProbe implements Probe
{
    public function __construct(private readonly array $config)
    {
    }

    public function ping(string $host, ?int $count = null, ?float $timeout = null): PingResult
    {
        $host = $this->validateHost($host);
        $count = $this->validateCount($count ?? $this->config['count']);
        $timeout = $this->validateTimeout($timeout ?? $this->config['timeout']);
        $command = $this->command($host, $count, $timeout);

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new IcmpException('Unable to start the ping command.');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return $this->parse($host, $count, trim($stdout."\n".$stderr), $exitCode);
    }

    /**
     * @param list<string> $hosts
     * @return list<PingResult>
     */
    public function pingMany(array $hosts, ?int $count = null, ?float $timeout = null, ?int $concurrency = null): array
    {
        $count = $this->validateCount($count ?? $this->config['count']);
        $timeout = $this->validateTimeout($timeout ?? $this->config['timeout']);
        $concurrency = $this->validateConcurrency($concurrency ?? (int) ($this->config['concurrency'] ?? 5));
        $hosts = array_map(fn (string $host) => $this->validateHost($host), $hosts);

        $pending = array_values($hosts);
        $running = [];
        $results = [];

        try {
            while ($pending !== [] || $running !== []) {
                while ($pending !== [] && count($running) < $concurrency) {
                    $index = count($hosts) - count($pending);
                    $host = array_shift($pending);
                    $process = proc_open($this->command($host, $count, $timeout), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

                    if (! is_resource($process)) {
                        throw new IcmpException('Unable to start the ping command.');
                    }

                    stream_set_blocking($pipes[1], false);
                    stream_set_blocking($pipes[2], false);
                    $running[$index] = compact('host', 'process', 'pipes') + ['stdout' => '', 'stderr' => ''];
                }

                foreach ($running as $index => &$job) {
                    $job['stdout'] .= stream_get_contents($job['pipes'][1]);
                    $job['stderr'] .= stream_get_contents($job['pipes'][2]);
                    $status = proc_get_status($job['process']);

                    if ($status['running']) {
                        continue;
                    }

                    $job['stdout'] .= stream_get_contents($job['pipes'][1]);
                    $job['stderr'] .= stream_get_contents($job['pipes'][2]);
                    fclose($job['pipes'][1]);
                    fclose($job['pipes'][2]);
                    $reportedExitCode = $status['exitcode'];
                    $closedExitCode = proc_close($job['process']);
                    $exitCode = $reportedExitCode >= 0 ? $reportedExitCode : $closedExitCode;
                    $results[$index] = $this->parse($job['host'], $count, trim($job['stdout']."\n".$job['stderr']), $exitCode);
                    unset($running[$index]);
                }
                unset($job);

                if ($running !== []) {
                    usleep(10_000);
                }
            }
        } finally {
            foreach ($running as $job) {
                foreach ($job['pipes'] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_terminate($job['process']);
                proc_close($job['process']);
            }
        }

        ksort($results);

        return array_values($results);
    }

    /** @return list<string> */
    private function command(string $host, int $count, float $timeout): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return ['ping', '-n', (string) $count, '-w', (string) (int) round($timeout * 1000), $host];
        }

        // macOS accepts milliseconds for -W, whereas Linux uses seconds.
        $wait = PHP_OS_FAMILY === 'Darwin'
            ? (string) max(1, (int) round($timeout * 1000))
            : (string) max(1, (int) ceil($timeout));

        return ['ping', '-n', '-c', (string) $count, '-W', $wait, $host];
    }

    private function parse(string $host, int $requestedCount, string $output, int $exitCode): PingResult
    {
        preg_match('/PING[^\n]*?\(([^)]+)\)|Pinging\s+[^\[]*\[([^]]+)]/i', $output, $address);
        $ip = $address[1] ?? $address[2] ?? null;
        $replies = [];

        foreach (preg_split('/\R/', $output) as $line) {
            if (preg_match('/(\d+)\s+bytes from\s+([^: ]+).*?icmp_seq[= ](\d+).*?ttl[= ](\d+).*?time[=<]([\d.]+)\s*ms/i', $line, $m)) {
                $replies[] = new PingReply((int) $m[3], (float) $m[5], (int) $m[4], (int) $m[1], $m[2]);
            } elseif (preg_match('/Reply from\s+([^:]+):\s+bytes=(\d+)\s+time[=<](\d+)ms\s+TTL=(\d+)/i', $line, $m)) {
                $replies[] = new PingReply(count($replies) + 1, (float) $m[3], (int) $m[4], (int) $m[2], trim($m[1]));
            }
        }

        preg_match('/(\d+)\s+(?:packets\s+)?transmitted.*?(\d+)\s+(?:packets\s+)?received.*?(\d+(?:\.\d+)?)%\s*(?:packet )?loss/i', $output, $summary);
        $transmitted = isset($summary[1]) ? (int) $summary[1] : $requestedCount;
        $received = isset($summary[2]) ? (int) $summary[2] : count($replies);
        $loss = isset($summary[3]) ? (float) $summary[3] : ($transmitted ? (100 * ($transmitted - $received) / $transmitted) : 100.0);

        if (preg_match('/Sent\s*=\s*(\d+).*?Received\s*=\s*(\d+).*?\((\d+)%\s*loss\)/i', $output, $windowsSummary)) {
            $transmitted = (int) $windowsSummary[1];
            $received = (int) $windowsSummary[2];
            $loss = (float) $windowsSummary[3];
        }

        preg_match('/(?:min\/avg\/max(?:\/[^\s]+)?|Minimum =)[^=]*=\s*([\d.]+)[^\/ ]*\s*\/\s*([\d.]+)[^\/ ]*\s*\/\s*([\d.]+)(?:\s*\/\s*([\d.]+))?/i', $output, $timing);
        $times = array_values(array_filter(array_map(fn (PingReply $reply) => $reply->timeMs, $replies), fn ($time) => $time !== null));

        if (preg_match('/Minimum\s*=\s*(\d+)ms.*?Maximum\s*=\s*(\d+)ms.*?Average\s*=\s*(\d+)ms/i', $output, $windowsTiming)) {
            $timing = ['', $windowsTiming[1], $windowsTiming[3], $windowsTiming[2]];
        }

        return new PingResult(
            $host, $ip, $transmitted, $received, $loss,
            isset($timing[1]) ? (float) $timing[1] : ($times ? min($times) : null),
            isset($timing[2]) ? (float) $timing[2] : ($times ? array_sum($times) / count($times) : null),
            isset($timing[3]) ? (float) $timing[3] : ($times ? max($times) : null),
            isset($timing[4]) ? (float) $timing[4] : null,
            $replies,
            $received === 0 ? ($output ?: "Ping failed with exit code {$exitCode}.") : null,
            $output,
        );
    }

    private function validateHost(string $host): string
    {
        $host = trim($host);
        if ($host === '' || preg_match('/[\x00-\x1F\x7F]/', $host)) {
            throw new IcmpException('Host must be a non-empty hostname or IP address.');
        }
        return $host;
    }

    private function validateCount(int $count): int
    {
        if ($count < 1 || $count > 100) throw new IcmpException('Count must be between 1 and 100.');
        return $count;
    }

    private function validateTimeout(float $timeout): float
    {
        $maximum = (float) ($this->config['max_timeout'] ?? 30);
        if ($timeout <= 0 || $timeout > $maximum) throw new IcmpException("Timeout must be between 0 and {$maximum} seconds.");
        return $timeout;
    }

    private function validateConcurrency(int $concurrency): int
    {
        if ($concurrency < 1 || $concurrency > 100) {
            throw new IcmpException('Concurrency must be between 1 and 100.');
        }

        return $concurrency;
    }
}
