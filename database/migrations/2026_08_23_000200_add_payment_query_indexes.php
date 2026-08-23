<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        $addIndex = static function (array $columns, string $name): void {
            $exists = collect(Schema::getIndexes('payments'))->contains(
                fn (array $index): bool => ($index['name'] ?? null) === $name,
            );

            if (! $exists) {
                Schema::table('payments', fn (Blueprint $table) => $table->index($columns, $name));
            }
        };

        $addIndex(['status', 'created_at'], 'idx_payments_status_created');
        $addIndex(['user_id', 'status', 'created_at'], 'idx_payments_user_status_created');
        $addIndex(
            ['teacher_id', 'status', 'teacher_payout_id', 'paid_at'],
            'idx_payments_teacher_payout_date',
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        $existing = collect(Schema::getIndexes('payments'))->pluck('name')->all();

        Schema::table('payments', function (Blueprint $table) use ($existing): void {
            foreach ([
                'idx_payments_status_created',
                'idx_payments_user_status_created',
                'idx_payments_teacher_payout_date',
            ] as $index) {
                if (in_array($index, $existing, true)) {
                    $table->dropIndex($index);
                }
            }
        });
    }
};
