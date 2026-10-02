<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->char('request_hash', 64);
            $table->smallInteger('response_code');
            $table->jsonb('response_body');
            $table->timestamp('created_at', 0)->useCurrent();

            $table->index('created_at', 'idx_idempotency_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};