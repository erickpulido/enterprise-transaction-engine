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
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_id', 64)->unique();
            $table->decimal('balance', 18, 4)->default(0.0000);
            $table->string('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at', 0)->useCurrent();
            $table->timestamp('updated_at', 0)->useCurrent()->useCurrentOnUpdate();
        });

        // Inyección de restricción CHECK nativa de PostgreSQL
        DB::statement('ALTER TABLE accounts ADD CONSTRAINT chk_positive_balance CHECK (balance >= 0.0000);');
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};