<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('group_materials', 'video_path')) {
            return;
        }

        Schema::table('group_materials', function (Blueprint $table): void {
            // Local uploads use private://..., while production uploads use a
            // validated private Vercel Blob URL. The original video_url column
            // remains reserved for external links such as YouTube.
            $table->text('video_path')->nullable()->after('video_url');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('group_materials', 'video_path')) {
            return;
        }

        Schema::table('group_materials', function (Blueprint $table): void {
            $table->dropColumn('video_path');
        });
    }
};
