<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->string('key', 20)->unique()->index();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('grade_levels')->insert([
            [
                'key' => 'grade_10',
                'name' => 'الصف العاشر',
                'name_en' => 'Grade 10',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'grade_11',
                'name' => 'الصف الحادي عشر',
                'name_en' => 'Grade 11',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'grade_12',
                'name' => 'الصف الثاني عشر',
                'name_en' => 'Grade 12',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'all',
                'name' => 'كل الصفوف',
                'name_en' => 'All Grades',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_levels');
    }
};
