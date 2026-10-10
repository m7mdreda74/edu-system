<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_levels', function (Blueprint $table): void {
            if (! Schema::hasColumn('grade_levels', 'curriculum')) {
                $table->string('curriculum', 30)->default('qatari')->after('stage')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('grade_levels', function (Blueprint $table): void {
            if (Schema::hasColumn('grade_levels', 'curriculum')) {
                $table->dropColumn('curriculum');
            }
        });
    }
};
