<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webrtc_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete(); // null = broadcast to all
            $table->string('type', 30); // offer | answer | ice-candidate | join | leave | chat | mute | raise-hand
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Participants heartbeat table (to know who's online)
        Schema::create('webrtc_participants', function (Blueprint $table) {
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

    public function down(): void
    {
        Schema::dropIfExists('webrtc_participants');
        Schema::dropIfExists('webrtc_signals');
    }
};
