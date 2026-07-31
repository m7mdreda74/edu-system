<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('points')->default(1)->after('type');
        });

        Schema::table('quiz_attempts', function (Blueprint $table): void {
            $table->unsignedInteger('earned_points')->default(0)->after('score');
            $table->unsignedInteger('total_points')->default(0)->after('earned_points');
        });

        Schema::table('private_session_slots', function (Blueprint $table): void {
            $table->boolean('is_free_intro')->default(false)->after('timezone')->index();
        });
    }

    public function down(): void
    {
        Schema::table('private_session_slots', function (Blueprint $table): void {
            $table->dropIndex(['is_free_intro']);
            $table->dropColumn('is_free_intro');
        });

        Schema::table('quiz_attempts', function (Blueprint $table): void {
            $table->dropColumn(['earned_points', 'total_points']);
        });

        Schema::table('quiz_questions', function (Blueprint $table): void {
            $table->dropColumn('points');
        });
    }
};
