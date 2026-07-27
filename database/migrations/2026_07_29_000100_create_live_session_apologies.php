<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_session_apologies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('live_session_id')->unique()->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('makeup_session_id')->nullable()->constrained('live_sessions')->nullOnDelete();
            $table->timestamp('makeup_scheduled_at')->nullable();
            $table->unsignedBigInteger('deduction_amount')->default(0);
            $table->text('admin_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('teacher_payout_id')->nullable()->constrained('teacher_payouts')->nullOnDelete();
            $table->timestamps();

            $table->index(['teacher_id', 'status']);
            $table->index(['teacher_id', 'teacher_payout_id']);
        });

        Schema::table('teacher_payouts', function (Blueprint $table): void {
            $table->unsignedBigInteger('deductions_amount')->default(0)->after('teacher_earnings');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_payouts', function (Blueprint $table): void {
            $table->dropColumn('deductions_amount');
        });

        Schema::dropIfExists('live_session_apologies');
    }
};
