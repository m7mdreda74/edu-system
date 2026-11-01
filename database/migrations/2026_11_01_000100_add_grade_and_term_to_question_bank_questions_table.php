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
            if (! Schema::hasColumn('question_bank_questions', 'grade_level_id')) {
                $table->foreignId('grade_level_id')
                    ->nullable()
                    ->after('subject_id')
                    ->constrained('grade_levels')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('question_bank_questions', 'academic_term_id')) {
                $table->foreignId('academic_term_id')
                    ->nullable()
                    ->after('grade_level_id')
                    ->constrained('academic_terms')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('question_bank_questions', function (Blueprint $table): void {
            if (Schema::hasColumn('question_bank_questions', 'academic_term_id')) {
                $table->dropConstrainedForeignId('academic_term_id');
            }

            if (Schema::hasColumn('question_bank_questions', 'grade_level_id')) {
                $table->dropConstrainedForeignId('grade_level_id');
            }
        });
    }
};

