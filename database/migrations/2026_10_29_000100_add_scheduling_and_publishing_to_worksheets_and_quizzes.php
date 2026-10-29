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
            if (! Schema::hasColumn('worksheets', 'available_from')) {
                $table->timestamp('available_from')->nullable();
            }
            if (! Schema::hasColumn('worksheets', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (! Schema::hasColumn('worksheets', 'teaching_group_id')) {
                $table->foreignId('teaching_group_id')->nullable()->constrained('teaching_groups')->nullOnDelete();
            }
        });

        Schema::table('quizzes', function (Blueprint $table): void {
            if (! Schema::hasColumn('quizzes', 'teaching_group_id')) {
                $table->foreignId('teaching_group_id')->nullable()->constrained('teaching_groups')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('worksheets', function (Blueprint $table): void {
            if (Schema::hasColumn('worksheets', 'teaching_group_id')) {
                $table->dropConstrainedForeignId('teaching_group_id');
            }
            if (Schema::hasColumn('worksheets', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('worksheets', 'available_from')) {
                $table->dropColumn('available_from');
            }
        });

        Schema::table('quizzes', function (Blueprint $table): void {
            if (Schema::hasColumn('quizzes', 'teaching_group_id')) {
                $table->dropConstrainedForeignId('teaching_group_id');
            }
        });
    }
};
