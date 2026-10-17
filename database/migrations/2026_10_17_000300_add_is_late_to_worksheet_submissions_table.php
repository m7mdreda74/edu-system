<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worksheet_submissions', function (Blueprint $table): void {
            $table->boolean('is_late')->default(false)->after('teacher_feedback')->index();
        });
    }

    public function down(): void
    {
        Schema::table('worksheet_submissions', function (Blueprint $table): void {
            $table->dropColumn('is_late');
        });
    }
};
