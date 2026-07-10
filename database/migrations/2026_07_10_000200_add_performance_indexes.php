<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance Indexes Migration.
 * Add compound indexes for the most common query patterns.
 * These dramatically reduce query time on large datasets.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── enrollments ──────────────────────────────────────────────────
        Schema::table('enrollments', function (Blueprint $table) {
            // Most common: "is this user enrolled in this course?"
            // Used in isEnrolledIn() — must be ultra-fast
            $table->index(['user_id', 'course_id'], 'idx_enrollments_user_course');

            // Teacher dashboard: enrollments by course
            $table->index(['course_id', 'progress_percent'], 'idx_enrollments_course_progress');
        });

        // ─── payments ─────────────────────────────────────────────────────
        Schema::table('payments', function (Blueprint $table) {
            // Webhook idempotency check — must be indexed
            $table->index('gateway_ref', 'idx_payments_gateway_ref');

            // Admin revenue queries by status + date
            $table->index(['status', 'paid_at'], 'idx_payments_status_date');

            // Student payment history
            $table->index(['user_id', 'status'], 'idx_payments_user_status');
        });

        // ─── courses ──────────────────────────────────────────────────────
        Schema::table('courses', function (Blueprint $table) {
            // Homepage featured courses query
            $table->index(['is_published', 'created_at'], 'idx_courses_published_date');

            // Filter by subject + grade
            $table->index(['subject_id', 'grade_level', 'is_published'], 'idx_courses_filter');
        });

        // ─── quiz_attempts ────────────────────────────────────────────────
        Schema::table('quiz_attempts', function (Blueprint $table) {
            // Check user attempts count
            $table->index(['user_id', 'quiz_id'], 'idx_quiz_attempts_user_quiz');
        });

        // ─── lesson_progress ─────────────────────────────────────────────
        Schema::table('lesson_progress', function (Blueprint $table) {
            // Progress recalculation — enrollment's completed lessons
            $table->index(['enrollment_id', 'is_completed'], 'idx_lesson_progress_completion');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('idx_enrollments_user_course');
            $table->dropIndex('idx_enrollments_course_progress');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_gateway_ref');
            $table->dropIndex('idx_payments_status_date');
            $table->dropIndex('idx_payments_user_status');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('idx_courses_published_date');
            $table->dropIndex('idx_courses_filter');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropIndex('idx_quiz_attempts_user_quiz');
        });

        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->dropIndex('idx_lesson_progress_completion');
        });
    }
};
