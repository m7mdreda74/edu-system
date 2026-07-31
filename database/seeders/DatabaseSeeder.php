<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Fills the platform with a complete, believable demo:
 * grade → subject → teacher → group → subscription → lesson.
 *
 * Grades, subjects and the curriculum linking them are NOT touched — they are
 * the Qatari MOEHE plan, owned by migrations. Everything else is wiped and
 * rebuilt, so seeding twice gives the same result.
 *
 * Money is stored in the smallest currency unit throughout: 45000 is 450 QAR.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Rebuilt on every run. `grade_levels`, `subjects` and
     * `grade_level_subject` are deliberately absent.
     */
    private const REBUILT_TABLES = [
        'users',
        'teaching_assignments', 'teaching_groups', 'teaching_group_schedules', 'teaching_group_lessons',
        'private_session_slots', 'session_bookings', 'subscriptions',
        'curriculum_units', 'group_materials', 'lesson_progress', 'lesson_questions',
        'quizzes', 'quiz_questions', 'quiz_options', 'quiz_attempts',
        'worksheets', 'worksheet_submissions',
        'coupons', 'payments', 'invoices', 'teacher_payouts', 'payment_audit_logs',
        'reviews', 'conversations', 'conversation_participants', 'chat_messages',
        'live_sessions', 'live_session_attendees',
        'parent_student_links', 'purchase_requests', 'private_lesson_requests', 'notifications',
        'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'roles', 'permissions',
    ];

    public function run(): void
    {
        $this->assertCurriculumExists();

        $this->step('🧹 تفريغ البيانات التجريبية (المنهج محفوظ)', fn () => $this->wipe());
        $this->step('👤 الحسابات — أدمن ومعلمين وطلاب وأولياء أمور', fn () => $this->call(AccountsSeeder::class));
        $this->step('📅 جداول التدريس والمجموعات والمواعيد',        fn () => $this->call(TeachingSeeder::class));
        $this->step('📚 المحتوى — مواد وخطط واختبارات وواجبات وحصص', fn () => $this->call(ContentSeeder::class));
        $this->step('💳 الاشتراكات والمدفوعات والكوبونات والتسويات', fn () => $this->call(CommerceSeeder::class));
        $this->step('💬 التفاعل — تقدّم ومحاولات وأسئلة ورسائل وتقييمات', fn () => $this->call(EngagementSeeder::class));
        $this->step('⚙️  إعدادات المنصة',                            fn () => $this->call(PlatformSettingsSeeder::class));

        $this->flushCaches();
        $this->report();
    }

    // ─── Steps ────────────────────────────────────────────────────

    private function assertCurriculumExists(): void
    {
        if (GradeLevel::count() === 0 || Subject::count() === 0) {
            throw new RuntimeException(
                'المنهج غير موجود. شغّل `php artisan migrate` أولاً — الصفوف والمواد تأتي من المايجريشن وليس من الـ seeder.',
            );
        }
    }

    private function wipe(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (self::REBUILT_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    private function flushCaches(): void
    {
        foreach (['platform_settings', 'home.grades', 'home.featured_teachers', 'admin_platform_stats'] as $key) {
            Cache::forget($key);
        }
    }

    private function step(string $label, callable $work): void
    {
        $this->command?->info($label . ' ...');
        $work();
    }

    // ─── Summary ──────────────────────────────────────────────────

    private function report(): void
    {
        $count = fn (string $table) => Schema::hasTable($table) ? DB::table($table)->count() : 0;

        $this->command?->newLine();
        $this->command?->info('✅ تم تجهيز المنصة.');

        $this->command?->table(['المحتوى', 'العدد'], [
            ['الصفوف الدراسية',     $count('grade_levels')],
            ['المواد',              $count('subjects')],
            ['روابط المنهج',        $count('grade_level_subject')],
            ['المعلمون',            DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->where('roles.name', 'teacher')->count()],
            ['الطلاب',              DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->where('roles.name', 'student')->count()],
            ['أولياء الأمور',       DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->where('roles.name', 'parent')->count()],
            ['تكليفات التدريس',     $count('teaching_assignments')],
            ['المجموعات',           $count('teaching_groups')],
            ['مواعيد الحصص الخاصة', $count('private_session_slots')],
            ['المواد التعليمية',    $count('group_materials')],
            ['الاختبارات',          $count('quizzes')],
            ['الواجبات والملازم',   $count('worksheets')],
            ['الحصص المباشرة',      $count('live_sessions')],
            ['الاشتراكات',          $count('subscriptions')],
            ['المدفوعات',           $count('payments')],
            ['التقييمات',           $count('reviews')],
            ['المحادثات',           $count('conversations')],
            ['طلبات الدروس الخاصة', $count('private_lesson_requests')],
            ['روابط ولي الأمر',     $count('parent_student_links')],
        ]);

        $this->command?->newLine();
        $this->command?->info('🔑 بيانات الدخول (كلمة المرور للجميع: ' . AccountsSeeder::PASSWORD . ')');

        $this->command?->table(['الدور', 'البريد'], [
            ['أدمن',    'admin@altafawwuq.com'],
            ['معلم',    'ahmed@altafawwuq.com'],
            ['معلمة',   'sara@altafawwuq.com'],
            ['طالب',    'student@altafawwuq.com'],
            ['ولي أمر', 'parent@altafawwuq.com'],
        ]);
    }
}
