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

        // Live sessions performance indexes
        $addIndex('live_sessions', ['teacher_id', 'status', 'scheduled_at'], 'idx_live_sessions_t_s_sched');
        $addIndex('live_sessions', ['teaching_group_id', 'status'], 'idx_live_sessions_group_status');
        $addIndex('live_sessions', ['private_session_slot_id', 'status'], 'idx_live_sessions_slot_status');

        // Attendee heartbeat & tracking compound indexes
        $addIndex('live_session_attendees', ['live_session_id', 'user_id', 'left_at'], 'idx_attendees_session_user_left');
        $addIndex('live_session_attendees', ['user_id', 'live_session_id'], 'idx_attendees_user_session');

        // Booking lookups & attendance eligibility indexes
        $addIndex('session_bookings', ['student_id', 'status'], 'idx_bookings_student_status');
        $addIndex('session_bookings', ['teaching_group_id', 'student_id', 'status'], 'idx_bookings_group_student_status');

        // Subscription lookups
        $addIndex('subscriptions', ['student_id', 'teaching_group_id', 'status'], 'idx_subscriptions_student_group_status');

        // Teaching structure lookups
        $addIndex('teaching_groups', ['teaching_assignment_id', 'is_active'], 'idx_teaching_groups_assignment_active');
        $addIndex('teaching_assignments', ['teacher_id', 'is_active'], 'idx_teaching_assignments_teacher_active');
    }

    public function down(): void
    {
        foreach ([
            'live_sessions' => ['idx_live_sessions_t_s_sched', 'idx_live_sessions_group_status', 'idx_live_sessions_slot_status'],
            'live_session_attendees' => ['idx_attendees_session_user_left', 'idx_attendees_user_session'],
            'session_bookings' => ['idx_bookings_student_status', 'idx_bookings_group_student_status'],
            'subscriptions' => ['idx_subscriptions_student_group_status'],
            'teaching_groups' => ['idx_teaching_groups_assignment_active'],
            'teaching_assignments' => ['idx_teaching_assignments_teacher_active'],
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
