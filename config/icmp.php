<?php

return [
    /* `command` uses the operating system ping binary; `raw` emits ICMP echo
       packets itself and usually requires root/CAP_NET_RAW privileges. */
    'driver' => env('ICMP_DRIVER', 'command'),

    'timeout' => (float) env('ICMP_TIMEOUT', 1.0),

    'count' => (int) env('ICMP_COUNT', 4),

    // Maximum command-driver probes that may run concurrently in pingMany().
    'concurrency' => (int) env('ICMP_CONCURRENCY', 5),

    // A hard cap protects workers from unreasonably long command execution.
    'max_timeout' => 30.0,
];
