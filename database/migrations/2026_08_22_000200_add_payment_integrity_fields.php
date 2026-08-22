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
        if (! Schema::hasColumn('payments', 'teacher_id')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->foreignId('teacher_id')->nullable()->after('subscription_id')
                    ->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('payments', 'receipt_sha256')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->char('receipt_sha256', 64)->nullable()->unique('payments_receipt_sha256_unique');
            });
        }

        // Preserve the teacher that owned historic payments before an
        // assignment is edited or moved to another teacher.
        if (Schema::hasColumn('payments', 'subscription_id')) {
            DB::statement(<<<'SQL'
                UPDATE payments
                SET teacher_id = (
                    SELECT teaching_assignments.teacher_id
                    FROM subscriptions
                    INNER JOIN teaching_assignments
                        ON teaching_assignments.id = subscriptions.teaching_assignment_id
                    WHERE subscriptions.id = payments.subscription_id
                )
                WHERE teacher_id IS NULL
            SQL);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'receipt_sha256')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropUnique('payments_receipt_sha256_unique');
                $table->dropColumn('receipt_sha256');
            });
        }

        if (Schema::hasColumn('payments', 'teacher_id')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropForeign(['teacher_id']);
                $table->dropColumn('teacher_id');
            });
        }
    }
};
