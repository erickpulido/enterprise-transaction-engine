<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ProjectTransactionToClickHouseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 2;

    public function __construct(public array $eventData) {}

    public function handle(): void
    {
        $cfg = config('clickhouse');
        $endpoint = sprintf('http://%s:%d/?query=', $cfg['host'], $cfg['port'])
            . urlencode("INSERT INTO {$cfg['database']}.transaction_ledger_events FORMAT JSONEachRow");

        $response = Http::timeout($cfg['timeout'])
            ->withHeaders([
                'X-ClickHouse-User' => $cfg['user'],
                'X-ClickHouse-Key'  => $cfg['password'],
            ])
            ->withBody(json_encode($this->eventData, JSON_THROW_ON_ERROR), 'application/json')
            ->post($endpoint);

        if ($response->failed()) {
            throw new RuntimeException("Fallo en inserción a ClickHouse: " . $response->body());
        }
    }
}