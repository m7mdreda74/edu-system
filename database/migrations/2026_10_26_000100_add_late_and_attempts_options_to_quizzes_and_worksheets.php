<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worksheets', function (Blueprint $table): void {
            if (! Schema::hasColumn('worksheets', 'allow_late_submission')) {
                $table->boolean('allow_late_submission')->default(true)->after('due_date');
            }
            if (! Schema::hasColumn('worksheets', 'max_attempts')) {
                $table->unsignedTinyInteger('max_attempts')->default(1)->after('allow_late_submission');
            }
        });

        Schema::table('quizzes', function (Blueprint $table): void {
            if (! Schema::hasColumn('quizzes', 'allow_late_submission')) {
                $table->boolean('allow_late_submission')->default(false)->after('available_until');
            }
            if (! Schema::hasColumn('quizzes', 'max_attempts')) {
                $table->unsignedTinyInteger('max_attempts')->default(3)->after('allow_late_submission');
            }
        });
    }

    public function down(): void
    {
        Schema::table('worksheets', function (Blueprint $table): void {
            $table->dropColumn(['allow_late_submission', 'max_attempts']);
        });

        Schema::table('quizzes', function (Blueprint $table): void {
            $table->dropColumn(['allow_late_submission', 'max_attempts']);
        });
    }
};
