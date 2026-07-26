<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly subscriptions replace one-off course purchases.
 *
 * A student subscribes either to a weekly group or to a teacher's private
 * tuition, and pays month by month. `teaching_assignment_id` is filled in for
 * both kinds — for group subscriptions it is denormalised from the group —
 * so "which teacher/subject is this student studying with" stays a single
 * indexed lookup instead of a join through groups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 10)->index(); // group | private

            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->cascadeOnDelete();
            $table->foreignId('teaching_group_id')->nullable()->constrained('teaching_groups')->cascadeOnDelete();

            $table->unsignedBigInteger('monthly_price');
            $table->string('currency', 3)->default('QAR');

            $table->date('period_start');
            $table->date('period_end')->index();

            // pending  → awaiting payment / admin verification of a manual transfer
            // active   → paid and inside its period
            // expired  → period elapsed without renewal
            // cancelled→ ended early by the student or an admin
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('auto_renew')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['teaching_group_id', 'status']);
            $table->index(['teaching_assignment_id', 'status']);

            // One subscription per student per group per billing month.
            $table->unique(['student_id', 'teaching_group_id', 'period_start'], 'subscriptions_group_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
