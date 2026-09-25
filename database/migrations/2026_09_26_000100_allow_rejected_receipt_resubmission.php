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
        if (! Schema::hasColumn('payments', 'receipt_sha256')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_receipt_sha256_unique');
            $table->index(['receipt_sha256', 'status'], 'idx_payments_receipt_hash_status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payments', 'receipt_sha256')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('idx_payments_receipt_hash_status');
        });

        $hasDuplicates = DB::table('payments')
            ->whereNotNull('receipt_sha256')
            ->select('receipt_sha256')
            ->groupBy('receipt_sha256')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if (! $hasDuplicates) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->unique('receipt_sha256', 'payments_receipt_sha256_unique');
            });
        }
    }
};
