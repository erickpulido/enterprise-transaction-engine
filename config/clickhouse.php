<?php

declare(strict_types=1);

return [
    'host'     => env('CLICKHOUSE_HOST', '127.0.0.1'),
    'port'     => (int) env('CLICKHOUSE_PORT', 8123),
    'database' => env('CLICKHOUSE_DATABASE', 'analytics'),
    'user'     => env('CLICKHOUSE_USER', 'default'),
    'password' => env('CLICKHOUSE_PASSWORD', ''),
    'timeout'  => (float) env('CLICKHOUSE_TIMEOUT', 2.0),
];