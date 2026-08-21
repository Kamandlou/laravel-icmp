# Laravel ICMP

[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel 10–12](https://img.shields.io/badge/Laravel-10%E2%80%9312-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)

A Laravel package for ICMP echo probes. It runs the operating system `ping` command or sends native IPv4 ICMP echo packets, then returns a consistent, structured result—no parsing in your application code.

## Requirements

- PHP 8.2 or later
- Laravel 10, 11, or 12
- The `ping` executable for the default `command` driver
- `ext-sockets` and raw-socket permission only when using the `raw` driver

## Installation

```bash
composer require kamandlou/laravel-icmp
```

Laravel package discovery registers the provider and `Icmp` facade automatically. Publish the configuration only if you need to change the defaults:

```bash
php artisan vendor:publish --tag=icmp-config
```

## Quick start

```php
use Kamandlou\LaravelIcmp\Facades\Icmp;

$result = Icmp::ping('1.1.1.1', count: 3, timeout: 1.5);

if ($result->successful()) {
    logger()->info('Host is reachable', $result->toArray());
} else {
    logger()->warning('Host did not reply', [
        'host' => $result->host,
        'error' => $result->error,
        'packet_loss' => $result->packetLoss,
    ]);
}
```

Arguments are optional. When omitted, the package uses the values in `config/icmp.php`.

```php
// Uses the configured count and timeout.
$result = Icmp::ping('example.com');
```

## Probing multiple hosts

Use `pingMany()` to probe multiple hosts while limiting how many operating-system
`ping` processes can run at the same time. Results retain the input order.

```php
$results = Icmp::pingMany([
    '1.1.1.1',
    '8.8.8.8',
    'example.com',
], count: 2, timeout: 1.0, concurrency: 3);
```

The command driver runs up to `concurrency` probes in parallel (default:
`ICMP_CONCURRENCY`, which defaults to 5; allowed range: 1–100). The raw driver processes hosts in order because its
shared socket receive queue cannot safely be multiplexed this way.

## Result format

`ping()` returns an immutable `PingResult`. Its camel-case properties are convenient in PHP; `toArray()` produces snake-case data suitable for JSON responses and logs.

```php
$result->toArray();
```

```php
[
    'host' => '1.1.1.1',
    'ip' => '1.1.1.1',
    'transmitted' => 3,
    'received' => 3,
    'packet_loss' => 0.0,
    'min_ms' => 10.2,
    'avg_ms' => 11.7,
    'max_ms' => 13.4,
    'stddev_ms' => 1.3,
    'successful' => true,
    'replies' => [
        [
            'sequence' => 0,
            'time_ms' => 10.2,
            'ttl' => 57,
            'bytes' => 64,
            'from' => '1.1.1.1',
            'error' => null,
        ],
    ],
    'error' => null,
    'raw_output' => '...', // command driver only
]
```

`successful()` is `true` when at least one reply was received and no package-level error was recorded. A failed probe still returns a `PingResult`; inspect `error`, `packetLoss`, and individual `replies` to report it appropriately.

## Drivers

### Command driver (default)

The `command` driver calls the local `ping` binary through `proc_open()` using an argument array, so the host is never interpolated into a shell command. It supports Linux, macOS, and Windows command formats.

```env
ICMP_DRIVER=command
```

This is the recommended driver for normal web servers, queues, and shared hosting. Ensure the `ping` binary is installed and available to the PHP process.

### Raw socket driver

The `raw` driver sends and receives native IPv4 ICMP echo packets using `ext-sockets`.

```env
ICMP_DRIVER=raw
```

Or select it per call:

```php
$result = app('icmp')->driver('raw')->ping('1.1.1.1', count: 2);
```

Raw sockets generally require elevated permissions. On Linux, grant only the required capability to the PHP executable (adapt the path to your deployment):

```bash
sudo setcap cap_net_raw+ep /usr/bin/php
```

Avoid running an entire PHP application as root. Containers and hosting providers may also block raw ICMP even when `ext-sockets` is enabled. If the package throws a raw-socket permission error, use the command driver or adjust your server policy.

## Configuration

Published configuration: `config/icmp.php`.

```php
return [
    // `command` or `raw`
    'driver' => env('ICMP_DRIVER', 'command'),

    // Timeout per echo request, in seconds.
    'timeout' => (float) env('ICMP_TIMEOUT', 1.0),

    // Number of echo requests sent for each probe.
    'count' => (int) env('ICMP_COUNT', 4),

    // Maximum parallel probes used by pingMany().
    'concurrency' => (int) env('ICMP_CONCURRENCY', 5),

    // Upper limit accepted for a caller-provided timeout.
    'max_timeout' => 30.0,
];
```

Counts must be between 1 and 100. Timeouts must be greater than zero and no greater than `max_timeout`.

## Using dependency injection

You may inject `IcmpManager` instead of using the facade:

```php
use Kamandlou\LaravelIcmp\IcmpManager;

class HealthCheckController
{
    public function __invoke(IcmpManager $icmp)
    {
        return response()->json($icmp->ping('1.1.1.1')->toArray());
    }
}
```

## Security and operational guidance

- Do not expose unrestricted user-supplied hosts, counts, or timeouts through a public endpoint. Even bounded probes consume worker time and network resources.
- Apply authorization and rate limits to network diagnostics endpoints.
- The command driver rejects control characters in a host and does not use shell interpolation.
- ICMP availability is not a complete health check: networks and firewalls can drop ICMP while an application service remains available. Pair it with service-specific checks when appropriate.

## Testing

```bash
composer install
composer test
```

The test suite is deterministic and does not send live packets. It covers command output parsing for Unix and Windows, validation, Laravel registration, structured values, and ICMP checksum construction.

## Troubleshooting

| Problem | Likely cause | Resolution |
| --- | --- | --- |
| `Unable to start the ping command` | `ping` is missing or unavailable to the PHP process | Install `ping` and ensure its directory is in `PATH`. |
| `Cannot open raw ICMP socket` | Missing `ext-sockets` or raw-socket privileges | Enable `ext-sockets`, grant `CAP_NET_RAW` where appropriate, or use `command`. |
| `received` is `0` | Host/network/firewall blocks ICMP | Verify from the same server and use a service-level health check if ICMP is blocked. |
| Command behaves differently on servers | OS ping implementations differ | The driver supports Linux/macOS/Windows; confirm the local binary is standard and accessible. |

## License

This package is released under the [MIT License](LICENSE.md).
