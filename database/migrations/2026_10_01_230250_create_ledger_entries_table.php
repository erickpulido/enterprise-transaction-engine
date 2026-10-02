<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('transaction_uuid');
            $table->foreignId('source_account_id')
                ->constrained('accounts')
                ->restrictOnDelete();
            $table->foreignId('destination_account_id')
                ->constrained('accounts')
                ->restrictOnDelete();
            $table->decimal('amount', 18, 4);
            $table->string('operation_type', 32);
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at', 0)->useCurrent();

            // Índices B-Tree compuestos para optimización de lecturas
            $table->index('transaction_uuid', 'idx_ledger_transaction_uuid');
            $table->index(['source_account_id', 'created_at'], 'idx_ledger_source_account');
            $table->index(['destination_account_id', 'created_at'], 'idx_ledger_destination_account');
        });

        // Inyección de restricciones CHECK nativas de PostgreSQL
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT chk_different_accounts CHECK (source_account_id <> destination_account_id);');
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT chk_positive_amount CHECK (amount > 0.0000);');
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};