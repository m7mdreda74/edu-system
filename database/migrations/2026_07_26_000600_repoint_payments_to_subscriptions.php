<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('subscription_id')->nullable()->after('user_id')
                ->constrained('subscriptions')->nullOnDelete();
        });

        foreach (DB::table('course_group_map')->cursor() as $map) {
            $payments = DB::table('payments')->where('course_id', $map->course_id)->get(['id', 'user_id']);

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

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['course_id', 'status']);
            $table->dropConstrainedForeignId('course_id');
            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('لا يمكن التراجع — الكورسات المرتبطة بهذه المدفوعات لم تعد موجودة.');
    }
};
