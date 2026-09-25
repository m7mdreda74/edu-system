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

        // Student and parent dashboards: filter by owner/status, then sort by
        // the date that is shown in the recent activity lists.
        $addIndex('subscriptions', ['student_id', 'status', 'period_end'], 'idx_subscriptions_student_status_period');
        $addIndex('worksheet_submissions', ['student_id', 'submitted_at'], 'idx_submissions_student_submitted');
        $addIndex('quiz_attempts', ['user_id', 'created_at'], 'idx_quiz_attempts_user_created');
        $addIndex('payments', ['user_id', 'created_at'], 'idx_payments_user_created');
        $addIndex('parent_student_links', ['parent_user_id', 'verified_at', 'student_user_id'], 'idx_parent_links_parent_verified_student');

        // Available private lessons are filtered by all three fields before
        // they are grouped by teaching assignment.
        $addIndex('private_session_slots', ['status', 'is_free_intro', 'starts_at', 'teaching_assignment_id'], 'idx_private_slots_status_intro_start_assignment');
        $addIndex('private_session_slots', ['teaching_assignment_id', 'status', 'starts_at'], 'idx_private_slots_assignment_status_start');

        // Curriculum/progress joins used by certificate and learning screens.
        $addIndex('group_materials', ['curriculum_unit_id', 'deleted_at'], 'idx_materials_unit_deleted');
        $addIndex('lesson_progress', ['student_id', 'is_completed', 'lesson_id'], 'idx_progress_student_completed_lesson');

        // Parent grade browsing and active-group listings.
        $addIndex('teaching_assignments', ['grade_level_id', 'is_active', 'teacher_id'], 'idx_assignments_grade_active_teacher');
        $addIndex('teaching_groups', ['is_active', 'teaching_assignment_id'], 'idx_groups_active_assignment');

        // NotificationBell summary/list requests on every authenticated page.
        $addIndex('notifications', ['notifiable_type', 'notifiable_id', 'read_at', 'created_at'], 'idx_notifications_recipient_read_created');
    }

    public function down(): void
    {
        foreach ([
            'subscriptions' => ['idx_subscriptions_student_status_period'],
            'worksheet_submissions' => ['idx_submissions_student_submitted'],
            'quiz_attempts' => ['idx_quiz_attempts_user_created'],
            'payments' => ['idx_payments_user_created'],
            'parent_student_links' => ['idx_parent_links_parent_verified_student'],
            'private_session_slots' => ['idx_private_slots_status_intro_start_assignment', 'idx_private_slots_assignment_status_start'],
            'group_materials' => ['idx_materials_unit_deleted'],
            'lesson_progress' => ['idx_progress_student_completed_lesson'],
            'teaching_assignments' => ['idx_assignments_grade_active_teacher'],
            'teaching_groups' => ['idx_groups_active_assignment'],
            'notifications' => ['idx_notifications_recipient_read_created'],
        ] as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existing = collect(Schema::getIndexes($table))->pluck('name')->all();

            foreach ($indexes as $index) {
                if (in_array($index, $existing, true)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index));
                }
            }
        }
    }
};
