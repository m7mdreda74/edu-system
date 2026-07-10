<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worksheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('course_lessons')->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('type', 20)->default('study'); // study | homework
            $table->boolean('requires_submission')->default(false);
            $table->date('due_date')->nullable();
            $table->unsignedSmallInteger('max_score')->nullable();
            $table->timestamps();
        });

        Schema::create('worksheet_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksheet_id')->constrained('worksheets')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('submitted_file_path');
            $table->unsignedSmallInteger('score')->nullable();
            $table->text('teacher_feedback')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worksheet_submissions');
        Schema::dropIfExists('worksheets');
    }
};
