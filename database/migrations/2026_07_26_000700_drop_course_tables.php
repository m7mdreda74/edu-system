<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retire courses.
 *
 * Everything that mattered has already been moved: content onto teaching
 * groups, students onto subscriptions and bookings, payments onto
 * subscriptions. What is left are the course rows themselves, the enrollments
 * that pointed at them, and the temporary bridge table.
 *
 * This is deliberately one-way — restoring it means restoring a backup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('course_group_map');

        Schema::enableForeignKeyConstraints();

        // The featured/subject caches were keyed off courses.
        if (Schema::hasTable('cache')) {
            DB::table('cache')->where('key', 'like', '%courses.featured%')->delete();
            DB::table('cache')->where('key', 'like', '%subjects.active%')->delete();
        }
    }

    public function down(): void
    {
        throw new RuntimeException('لا يمكن التراجع عن حذف الكورسات — استرجع نسخة احتياطية من قاعدة البيانات.');
    }
};
