<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('kind', 20)->default('academic')->after('id')->index();
            $table->foreignId('context_student_id')->nullable()->after('kind')
                ->constrained('users')->nullOnDelete();
            $table->string('subject')->nullable()->after('teacher_id');

            $table->foreignId('teaching_assignment_id')->nullable()->change();
            $table->foreignId('student_id')->nullable()->change();
            $table->foreignId('teacher_id')->nullable()->change();
        });

        Schema::create('conversation_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('participant_role', 20)->default('member');
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'conversation_id']);
        });

        foreach (DB::table('conversations')->get(['id', 'student_id', 'teacher_id']) as $conversation) {
            foreach ([
                [$conversation->student_id, 'student'],
                [$conversation->teacher_id, 'teacher'],
            ] as [$userId, $role]) {
                if ($userId) {
                    DB::table('conversation_participants')->insertOrIgnore([
                        'conversation_id' => $conversation->id,
                        'user_id' => $userId,
                        'participant_role' => $role,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('conversations')->where('kind', 'support')->delete();

        Schema::dropIfExists('conversation_participants');

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('context_student_id');
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'subject']);

            $table->foreignId('teaching_assignment_id')->nullable(false)->change();
            $table->foreignId('student_id')->nullable(false)->change();
            $table->foreignId('teacher_id')->nullable(false)->change();
        });
    }
};
