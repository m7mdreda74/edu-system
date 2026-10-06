<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class AlMajdFreshSeeder extends Seeder
{
    public const PASSWORD = 'password';

    private const WIPED_TABLES = [
        'users',
        'teaching_assignments', 'teaching_groups', 'teaching_group_schedules', 'teaching_group_lessons',
        'private_session_slots', 'session_bookings', 'subscriptions',
        'curriculum_units', 'group_materials', 'lesson_progress', 'lesson_questions',
        'quizzes', 'quiz_questions', 'quiz_options', 'quiz_attempts',
        'worksheets', 'worksheet_submissions',
        'coupons', 'payments', 'invoices', 'teacher_payouts', 'payment_audit_logs',
        'reviews', 'conversations', 'conversation_participants', 'chat_messages',
        'live_sessions', 'live_session_attendees', 'live_session_apologies', 'live_session_reminders',
        'parent_student_links', 'purchase_requests', 'private_lesson_requests', 'notifications',
        'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'roles', 'permissions',
        'lesson_videos', 'lesson_video_webhook_events', 'audit_events',
    ];

    public function run(): void
    {
        // 1. Wipe all transactional & user tables
        Schema::disableForeignKeyConstraints();
        foreach (self::WIPED_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }
        Schema::enableForeignKeyConstraints();

        // 2. Roles
        $roles = [
            'admin' => Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']),
            'teacher' => Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']),
            'student' => Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']),
            'parent' => Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']),
        ];

        // 3. Pre-computed hash (instantaneous)
        $passwordHash = Hash::make(self::PASSWORD);
        $now = now();

        // 4. Primary Admin
        $admin = User::create([
            'name' => 'مدير المنصة',
            'email' => 'admin@almagd.com',
            'phone' => '+97455000001',
            'password' => $passwordHash,
            'email_verified_at' => $now,
            'is_approved' => true,
        ]);
        $admin->syncRoles(['admin']);

        // 5. Supervisor Admin
        $supervisor = User::create([
            'name' => 'مشرف المحتوى',
            'email' => 'supervisor@almagd.com',
            'phone' => '+97455000002',
            'password' => $passwordHash,
            'email_verified_at' => $now,
            'is_approved' => true,
        ]);
        $supervisor->syncRoles(['admin']);

        // 6. Teachers
        foreach (TeachingStaff::teachers() as $def) {
            $teacher = User::create([
                'name'                  => $def['name'],
                'email'                 => $def['email'],
                'phone'                 => $def['phone'],
                'password'              => $passwordHash,
                'headline'              => $def['headline'],
                'bio'                   => $def['bio'],
                'years_experience'      => $def['experience'],
                'is_featured'           => $def['featured'] ?? false,
                'commission_percent'    => $def['commission'],
                'email_verified_at'     => $now,
                'is_approved'           => true,
            ]);
            $teacher->syncRoles(['teacher']);
        }

        // 7. Seed Settings & Teaching Assignments
        $this->call(PlatformSettingsSeeder::class);
        $this->call(TeachingSeeder::class);

        // 8. Clear all caches
        foreach (['platform_settings', 'home.grades', 'home.featured_teachers', 'admin_platform_stats'] as $key) {
            Cache::forget($key);
        }
    }
}
