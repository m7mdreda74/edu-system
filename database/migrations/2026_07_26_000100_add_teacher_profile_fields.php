<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teacher profile enrichment.
 *
 * The platform is teacher-centric now: a student browses grade → subject →
 * teachers, watches each teacher's intro video to judge their teaching style,
 * and books with whoever they like. That makes the teacher profile the main
 * conversion surface, so it needs more than name + bio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('headline')->nullable()->after('bio');
            $table->string('intro_video_url')->nullable()->after('headline');
            $table->string('intro_video_thumbnail')->nullable()->after('intro_video_url');
            $table->unsignedTinyInteger('years_experience')->nullable()->after('intro_video_thumbnail');
            $table->boolean('is_featured')->default(false)->index()->after('years_experience');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'headline',
                'intro_video_url',
                'intro_video_thumbnail',
                'years_experience',
                'is_featured',
            ]);
        });
    }
};
