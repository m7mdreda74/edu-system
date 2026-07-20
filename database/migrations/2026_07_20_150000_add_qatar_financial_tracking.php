<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedTinyInteger('commission_percent')->nullable()->after('is_active');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedTinyInteger('commission_percent')->nullable()->after('amount');
            $table->unsignedBigInteger('platform_commission_amount')->nullable()->after('commission_percent');
            $table->unsignedBigInteger('teacher_earnings')->nullable()->after('platform_commission_amount');
            $table->foreignId('teacher_payout_id')->nullable()->after('purchase_request_id')->constrained('teacher_payouts')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->after('teacher_payout_id')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
            $table->index(['teacher_payout_id', 'status']);
        });

        Schema::table('teacher_payouts', function (Blueprint $table): void {
            $table->unsignedBigInteger('gross_amount')->nullable()->after('amount');
            $table->unsignedBigInteger('teacher_earnings')->nullable()->after('gross_amount');
            $table->unsignedBigInteger('platform_commission_amount')->nullable()->after('teacher_earnings');
            $table->string('receipt_path')->nullable()->after('paid_at');
            $table->foreignId('paid_by')->nullable()->after('receipt_path')->constrained('users')->nullOnDelete();
            $table->timestamp('teacher_acknowledged_at')->nullable()->after('paid_by');
            $table->text('teacher_acknowledgment_note')->nullable()->after('teacher_acknowledged_at');
        });

        $defaultCommission = (int) (DB::table('platform_settings')->where('key', 'commission_percent')->value('value') ?? 20);
        DB::table('payments')->where('status', 'paid')->whereNull('teacher_earnings')->orderBy('id')->chunkById(100, function ($payments) use ($defaultCommission): void {
            foreach ($payments as $payment) {
                $teacherId = DB::table('courses')->where('id', $payment->course_id)->value('teacher_id');
                $teacherCommission = $teacherId ? DB::table('users')->where('id', $teacherId)->value('commission_percent') : null;
                $percent = max(0, min(100, (int) ($teacherCommission ?? $defaultCommission)));
                $platformAmount = (int) floor(($payment->amount * $percent) / 100);
                DB::table('payments')->where('id', $payment->id)->update([
                    'commission_percent' => $percent,
                    'platform_commission_amount' => $platformAmount,
                    'teacher_earnings' => $payment->amount - $platformAmount,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('teacher_payouts', function (Blueprint $table): void {
            $table->dropForeign(['paid_by']);
            $table->dropColumn(['gross_amount', 'teacher_earnings', 'platform_commission_amount', 'receipt_path', 'paid_by', 'teacher_acknowledged_at', 'teacher_acknowledgment_note']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['teacher_payout_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['teacher_payout_id', 'status']);
            $table->dropColumn(['commission_percent', 'platform_commission_amount', 'teacher_earnings', 'teacher_payout_id', 'reviewed_by', 'reviewed_at', 'review_notes']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('commission_percent');
        });
    }
};
