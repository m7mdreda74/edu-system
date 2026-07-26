<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A payment now settles a month of subscription instead of buying a course.
 *
 * Historic payments are matched to the subscription that was generated from the
 * student's enrollment on the same group, so revenue reporting and teacher
 * payouts keep their history. Anything unmatchable keeps its amount and status
 * but simply has no subscription attached.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'course_id')) {
            return; // Already applied.
        }

        if (! Schema::hasColumn('payments', 'subscription_id')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->foreignId('subscription_id')->nullable()->after('user_id')
                    ->constrained('subscriptions')->nullOnDelete();
            });
        }

        foreach (DB::table('course_group_map')->cursor() as $map) {
            $payments = DB::table('payments')
                ->where('course_id', $map->course_id)
                ->whereNull('subscription_id')
                ->get(['id', 'user_id']);

            foreach ($payments as $payment) {
                $subscriptionId = DB::table('subscriptions')
                    ->where('student_id', $payment->user_id)
                    ->where('teaching_group_id', $map->teaching_group_id)
                    ->orderBy('period_start')
                    ->value('id');

                if ($subscriptionId) {
                    DB::table('payments')->where('id', $payment->id)->update(['subscription_id' => $subscriptionId]);
                }
            }
        }

        // MySQL keeps a foreign key's backing index alive, so the constraint
        // must go before the index. SQLite has no standalone constraint — it
        // rebuilds the table — so the key and column go together instead.
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        if (! $isSqlite) {
            foreach (Schema::getForeignKeys('payments') as $foreignKey) {
                if (in_array('course_id', $foreignKey['columns'], true)) {
                    Schema::table('payments', fn (Blueprint $table) => $table->dropForeign($foreignKey['name']));
                }
            }
        }

        foreach (Schema::getIndexes('payments') as $index) {
            if (($index['primary'] ?? false) || ! in_array('course_id', $index['columns'], true)) {
                continue;
            }

            Schema::table('payments', fn (Blueprint $table) => $table->dropIndex($index['name']));
        }

        Schema::table('payments', function (Blueprint $table) use ($isSqlite): void {
            $isSqlite
                ? $table->dropConstrainedForeignId('course_id')
                : $table->dropColumn('course_id');

            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('لا يمكن التراجع — الكورسات المرتبطة بهذه المدفوعات لم تعد موجودة.');
    }
};
