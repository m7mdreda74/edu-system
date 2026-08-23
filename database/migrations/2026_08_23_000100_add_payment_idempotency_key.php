<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'idempotency_key')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->string('idempotency_key', 64)
                    ->nullable()
                    ->unique('payments_idempotency_key_unique')
                    ->after('receipt_sha256');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'idempotency_key')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropUnique('payments_idempotency_key_unique');
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
