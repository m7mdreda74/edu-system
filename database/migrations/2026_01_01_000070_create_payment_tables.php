<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->index();
            $table->unsignedTinyInteger('discount_percent'); // 1-100
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->unsignedBigInteger('amount');          // amount in smallest unit (halala/cent)
            $table->unsignedBigInteger('original_amount'); // before discount
            $table->string('currency', 3)->default('QAR');
            $table->string('gateway', 20);                 // stripe, fatora, skipcash
            $table->string('gateway_ref')->nullable()->index(); // gateway's transaction ID
            $table->string('status', 20)->default('pending')->index(); // pending, paid, failed, refunded
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['course_id', 'status']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('pdf_path')->nullable();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('teacher_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount'); // in smallest unit
            $table->string('currency', 3)->default('QAR');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('pending')->index(); // pending, paid
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('teacher_payouts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('coupons');
    }
};
