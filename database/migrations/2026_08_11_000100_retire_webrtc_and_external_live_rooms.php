<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // WebRTC signals/presence were transient transport state. Jitsi now
        // owns conference signalling, participants, and collaboration.
        Schema::dropIfExists('webrtc_signals');
        Schema::dropIfExists('webrtc_participants');

        if (Schema::hasColumn('live_sessions', 'room_id')) {
            Schema::table('live_sessions', function (Blueprint $table): void {
                $table->dropColumn('room_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('live_sessions', 'room_id')) {
            Schema::table('live_sessions', function (Blueprint $table): void {
                $table->string('room_id')->nullable();
            });
        }

        if (! Schema::hasTable('webrtc_signals')) {
            Schema::create('webrtc_signals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
                $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 30);
                $table->json('payload');
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }

        if (! Schema::hasTable('webrtc_participants')) {
            Schema::create('webrtc_participants', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name');
                $table->boolean('is_teacher')->default(false);
                $table->timestamp('last_seen_at')->index();
                $table->timestamps();
                $table->unique(['live_session_id', 'user_id']);
                $table->index(['live_session_id', 'last_seen_at']);
            });
        }
    }
};
