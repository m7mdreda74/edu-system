<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quiz_questions')) {
            Schema::table('quiz_questions', function (Blueprint $table): void {
                $table->longText('question_text')->change();
                if (Schema::hasColumn('quiz_questions', 'model_answer')) {
                    $table->longText('model_answer')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('question_bank_questions')) {
            Schema::table('question_bank_questions', function (Blueprint $table): void {
                $table->longText('question_text')->change();
                if (Schema::hasColumn('question_bank_questions', 'model_answer')) {
                    $table->longText('model_answer')->nullable()->change();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('quiz_questions')) {
            Schema::table('quiz_questions', function (Blueprint $table): void {
                $table->text('question_text')->change();
                if (Schema::hasColumn('quiz_questions', 'model_answer')) {
                    $table->text('model_answer')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('question_bank_questions')) {
            Schema::table('question_bank_questions', function (Blueprint $table): void {
                $table->text('question_text')->change();
                if (Schema::hasColumn('question_bank_questions', 'model_answer')) {
                    $table->text('model_answer')->nullable()->change();
                }
            });
        }
    }
};
