<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('curriculum_unit_id')->nullable()->constrained('curriculum_units')->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained('group_materials')->nullOnDelete();
            $table->text('question_text');
            $table->string('type', 20)->default('single');
            $table->unsignedSmallInteger('points')->default(1);
            $table->string('topic', 160)->nullable();
            $table->string('difficulty', 20)->default('medium');
            $table->text('explanation')->nullable();
            $table->json('tags')->nullable();
            $table->string('visibility', 20)->default('private');
            $table->timestamps();

            $table->index(['teacher_id', 'subject_id']);
            $table->index(['visibility', 'subject_id']);
        });

        Schema::create('question_bank_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_bank_question_id')
                ->constrained('question_bank_questions')
                ->cascadeOnDelete();
            $table->string('option_text');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->foreignId('question_bank_question_id')
                ->nullable()
                ->after('quiz_id')
                ->constrained('question_bank_questions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('question_bank_question_id');
        });

        Schema::dropIfExists('question_bank_options');
        Schema::dropIfExists('question_bank_questions');
    }
};
