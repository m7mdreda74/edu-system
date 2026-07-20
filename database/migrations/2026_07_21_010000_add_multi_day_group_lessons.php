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
        Schema::create('teaching_group_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_group_id')->constrained('teaching_groups')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamps();
            $table->unique(['teaching_group_id', 'day_of_week']);
            $table->index(['day_of_week', 'start_time', 'end_time']);
        });

        Schema::create('teaching_group_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_group_id')->constrained('teaching_groups')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('live_session_id')->nullable()->constrained('live_sessions')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
            $table->unique(['teaching_group_id', 'position']);
        });

        $now = now();
        foreach (DB::table('teaching_groups')->get() as $group) {
            DB::table('teaching_group_schedules')->insert([
                'teaching_group_id' => $group->id,
                'day_of_week' => $group->day_of_week,
                'start_time' => $group->start_time,
                'end_time' => $group->end_time,
                'duration_minutes' => $group->duration_minutes,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_group_lessons');
        Schema::dropIfExists('teaching_group_schedules');
    }
};
