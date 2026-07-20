<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['teacher_id', 'subject_id', 'grade_level_id']);
        });

        Schema::create('teaching_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('capacity');
            $table->unsignedTinyInteger('day_of_week'); // 0 Sunday ... 6 Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('timezone', 64)->default('Asia/Qatar');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['teaching_assignment_id', 'name']);
            $table->index(['day_of_week', 'start_time', 'end_time']);
        });

        Schema::create('private_session_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->cascadeOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->string('timezone', 64)->default('Asia/Qatar');
            $table->string('status', 20)->default('available')->index(); // available, booked, cancelled
            $table->timestamps();

            $table->unique(['teaching_assignment_id', 'starts_at', 'ends_at'], 'private_session_slots_unique_assignment_time');
            $table->index(['starts_at', 'ends_at', 'status']);
        });

        Schema::create('session_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teaching_group_id')->nullable()->constrained('teaching_groups')->cascadeOnDelete();
            $table->foreignId('private_session_slot_id')->nullable()->unique()->constrained('private_session_slots')->cascadeOnDelete();
            $table->string('status', 20)->default('confirmed')->index(); // confirmed, cancelled
            $table->text('notes')->nullable();
            $table->timestamp('booked_at')->useCurrent();
            $table->timestamps();

            $table->unique(['student_id', 'teaching_group_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_bookings');
        Schema::dropIfExists('private_session_slots');
        Schema::dropIfExists('teaching_groups');
        Schema::dropIfExists('teaching_assignments');
    }
};
