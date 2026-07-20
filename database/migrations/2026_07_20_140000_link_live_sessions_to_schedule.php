<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->foreignId('teaching_group_id')->nullable()->after('teacher_id')->constrained('teaching_groups')->nullOnDelete();
            $table->foreignId('private_session_slot_id')->nullable()->after('teaching_group_id')->constrained('private_session_slots')->nullOnDelete();
            $table->index(['teaching_group_id', 'scheduled_at']);
            $table->index(['private_session_slot_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->dropForeign(['teaching_group_id']);
            $table->dropForeign(['private_session_slot_id']);
            $table->dropIndex(['teaching_group_id', 'scheduled_at']);
            $table->dropIndex(['private_session_slot_id', 'scheduled_at']);
            $table->dropColumn(['teaching_group_id', 'private_session_slot_id']);
        });
    }
};
