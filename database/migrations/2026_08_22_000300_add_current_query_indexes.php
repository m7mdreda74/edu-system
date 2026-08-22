<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes match the current subscriptions/groups/live-session model. The
     * older courses/enrollments migration is intentionally left untouched so
     * rollback history remains valid.
     */
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

        $addIndex('live_sessions', ['status', 'scheduled_at'], 'idx_live_sessions_status_scheduled');
        $addIndex('live_sessions', ['teacher_id', 'scheduled_at'], 'idx_live_sessions_teacher_scheduled');
        $addIndex('live_session_attendees', ['joined_at', 'live_session_id'], 'idx_attendees_joined_session');
        $addIndex('session_bookings', ['teaching_group_id', 'status'], 'idx_bookings_group_status');
        $addIndex('session_bookings', ['private_session_slot_id', 'status'], 'idx_bookings_private_status');
        $addIndex('conversations', ['last_message_at', 'id'], 'idx_conversations_recent');
    }

    public function down(): void
    {
        foreach ([
            'live_sessions' => ['idx_live_sessions_status_scheduled', 'idx_live_sessions_teacher_scheduled'],
            'live_session_attendees' => ['idx_attendees_joined_session'],
            'session_bookings' => ['idx_bookings_group_status', 'idx_bookings_private_status'],
            'conversations' => ['idx_conversations_recent'],
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
