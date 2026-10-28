<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_bank_questions', function (Blueprint $table): void {
            $table->boolean('allow_file_upload')->default(false)->after('points');
            $table->text('model_answer')->nullable()->after('explanation');
        });

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->boolean('allow_file_upload')->default(false)->after('points');
            $table->text('model_answer')->nullable()->after('allow_file_upload');
        });

        Schema::table('quiz_attempts', function (Blueprint $table): void {
            $table->json('answers')->nullable()->after('quiz_id');
            $table->string('status', 30)->default('graded')->after('passed');
            $table->timestamp('graded_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table): void {
            $table->dropColumn(['answers', 'status', 'graded_at']);
        });

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->dropColumn(['allow_file_upload', 'model_answer']);
        });

        Schema::table('question_bank_questions', function (Blueprint $table): void {
            $table->dropColumn(['allow_file_upload', 'model_answer']);
        });
    }
};
