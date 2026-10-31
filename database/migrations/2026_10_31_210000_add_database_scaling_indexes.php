<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addIndex = static function (string $table, array $columns, string $name): void {
            if (! Schema::hasTable($table)) {
                return;
            }

            $exists = collect(Schema::getIndexes($table))->contains(
                fn (array $index): bool => ($index['name'] ?? null) === $name,
            );

            if (! $exists) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
            }
        };

        // Quiz attempts lookups for teacher dashboards and grading
        $addIndex('quiz_attempts', ['quiz_id', 'created_at'], 'idx_quiz_attempts_quiz_created');
        $addIndex('quiz_attempts', ['quiz_id', 'score'], 'idx_quiz_attempts_quiz_score');

        // Admin creator attribution lookups
        $addIndex('users', ['created_by'], 'idx_users_created_by');
        $addIndex('coupons', ['created_by'], 'idx_coupons_created_by');
        $addIndex('teaching_groups', ['created_by'], 'idx_teaching_groups_created_by');

        // Payment review and student status compound lookups
        $addIndex('payments', ['reviewed_by', 'status'], 'idx_payments_reviewed_status');
        $addIndex('payments', ['user_id', 'status', 'created_at'], 'idx_payments_user_status_created');
    }

    public function down(): void
    {
        $dropIndex = static function (string $table, string $name): void {
            if (! Schema::hasTable($table)) {
                return;
            }

            $exists = collect(Schema::getIndexes($table))->contains(
                fn (array $index): bool => ($index['name'] ?? null) === $name,
            );

            if ($exists) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
            }
        };

        $dropIndex('quiz_attempts', 'idx_quiz_attempts_quiz_created');
        $dropIndex('quiz_attempts', 'idx_quiz_attempts_quiz_score');
        $dropIndex('users', 'idx_users_created_by');
        $dropIndex('coupons', 'idx_coupons_created_by');
        $dropIndex('teaching_groups', 'idx_teaching_groups_created_by');
        $dropIndex('payments', 'idx_payments_reviewed_status');
        $dropIndex('payments', 'idx_payments_user_status_created');
    }
};
