<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\TeacherReview;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Payment\Models\Coupon;
use App\Domain\Payment\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizOption;
use App\Domain\Quiz\Models\QuizQuestion;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\TeachingGroupSchedule;
use App\Domain\Settings\Models\PlatformSetting;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Seeds a working teacher-centric platform:
 * grade → subject → teachers → groups → subscriptions.
 *
 * Prices are in the smallest currency unit (dirham of QAR), matching the
 * schema — 15000 is 150 riyals.
 */
class DatabaseSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $this->command->info('🧹 تفريغ الجداول...');
        $this->truncateAll();

        $this->command->info('🔑 إنشاء الأدوار...');
        $this->seedRoles();

        $this->command->info('🎓 المراحل والمواد...');
        [$grades, $subjects] = $this->seedAcademics();

        $this->command->info('👤 المستخدمون...');
        [$teachers, $students, $parent] = $this->seedUsers($grades);

        $this->command->info('📅 جداول التدريس والمجموعات...');
        $groups = $this->seedTeaching($teachers, $subjects, $grades);

        $this->command->info('📚 المحتوى التعليمي...');
        $this->seedContent($groups);

        $this->command->info('💳 الاشتراكات والمدفوعات...');
        $this->seedSubscriptions($students, $groups);

        $this->command->info('⭐ التقييمات وروابط أولياء الأمور...');
        $this->seedSocial($teachers, $students, $parent);

        $this->command->info('⚙️  إعدادات المنصة...');
        $this->seedSettings();

        $this->command->newLine();
        $this->command->info('✅ تم التجهيز. بيانات الدخول:');
        $this->command->table(
            ['الدور', 'البريد', 'كلمة المرور'],
            [
                ['أدمن',        'admin@altafawwuq.com',   self::PASSWORD],
                ['معلم',        'ahmed@altafawwuq.com',   self::PASSWORD],
                ['طالب',        'student@altafawwuq.com', self::PASSWORD],
                ['ولي أمر',     'parent@altafawwuq.com',  self::PASSWORD],
            ],
        );
    }

    // ─── Steps ────────────────────────────────────────────────────

    private function truncateAll(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'users',
            'teaching_assignments', 'teaching_groups', 'teaching_group_schedules', 'teaching_group_lessons',
            'private_session_slots', 'session_bookings', 'subscriptions',
            'group_materials', 'lesson_progress', 'lesson_questions',
            'quizzes', 'quiz_questions', 'quiz_options', 'quiz_attempts',
            'worksheets', 'worksheet_submissions',
            'coupons', 'payments', 'invoices', 'teacher_payouts', 'payment_audit_logs',
            'reviews', 'conversations', 'chat_messages',
            'live_sessions', 'live_session_attendees',
            'parent_student_links', 'purchase_requests', 'notifications',
            'model_has_roles', 'role_has_permissions', 'roles', 'permissions',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    private function seedRoles(): void
    {
        foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    /**
     * Grades, subjects and the curriculum linking them are seeded by the
     * migration that lays in the Qatari plan — this only reads them, so
     * re-seeding demo data never wipes the curriculum.
     *
     * @return array{0: array<string, GradeLevel>, 1: array<string, Subject>}
     */
    private function seedAcademics(): array
    {
        $grades   = GradeLevel::where('is_active', true)->get()->keyBy('key');
        $subjects = Subject::where('is_active', true)->get()->keyBy('name');

        if ($grades->isEmpty() || $subjects->isEmpty()) {
            throw new RuntimeException('المنهج غير موجود — شغّل `php artisan migrate` أولاً.');
        }

        return [$grades->all(), $subjects->all()];
    }

    /** @return array{0: array<string, User>, 1: array<int, User>, 2: User} */
    private function seedUsers(array $grades): array
    {
        $admin = $this->makeUser('admin', [
            'name'  => 'مدير المنصة',
            'email' => 'admin@altafawwuq.com',
            'phone' => '+97455000001',
        ]);

        $teacherDefinitions = [
            [
                'name'             => 'أ. أحمد الكواري',
                'email'            => 'ahmed@altafawwuq.com',
                'phone'            => '+97455000101',
                'headline'         => 'معلم رياضيات — 12 سنة خبرة في المنهج القطري',
                'bio'              => 'أبسّط الرياضيات بأسلوب عملي يربط كل قاعدة بمثال من الامتحانات الوزارية، وأركّز على بناء الفهم قبل الحفظ.',
                'years_experience' => 12,
                'is_featured'      => true,
                'intro_video_url'  => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ],
            [
                'name'             => 'أ. سارة المهندي',
                'email'            => 'sara@altafawwuq.com',
                'phone'            => '+97455000102',
                'headline'         => 'معلمة فيزياء وكيمياء — شرح بالتجارب',
                'bio'              => 'كل درس عندي يبدأ بتجربة أو موقف من الحياة اليومية، عشان المعلومة تثبت من أول مرة.',
                'years_experience' => 8,
                'is_featured'      => true,
                'intro_video_url'  => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ],
            [
                'name'             => 'أ. خالد آل ثاني',
                'email'            => 'khaled@altafawwuq.com',
                'phone'            => '+97455000103',
                'headline'         => 'معلم أحياء ولغة عربية',
                'bio'              => 'أسلوبي يعتمد على الخرائط الذهنية والملخصات المركّزة قبل الامتحانات مباشرة.',
                'years_experience' => 6,
                'intro_video_url'  => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ],
        ];

        $teachers = [];

        foreach ($teacherDefinitions as $definition) {
            $teachers[$definition['email']] = $this->makeUser('teacher', [
                ...$definition,
                'commission_percent' => 20,
            ]);
        }

        $studentDefinitions = [
            ['name' => 'محمد رضا',   'email' => 'student@altafawwuq.com', 'phone' => '+97455000201', 'grade_level' => 'grade_12_science'],
            ['name' => 'نورة العلي', 'email' => 'noura@altafawwuq.com',   'phone' => '+97455000202', 'grade_level' => 'grade_11_science'],
            ['name' => 'عبدالله سعد', 'email' => 'abdullah@altafawwuq.com', 'phone' => '+97455000203', 'grade_level' => 'grade_12_science'],
            ['name' => 'مريم الباكر', 'email' => 'maryam@altafawwuq.com',  'phone' => '+97455000204', 'grade_level' => 'grade_10'],
        ];

        $students = [];

        foreach ($studentDefinitions as $definition) {
            $students[] = $this->makeUser('student', $definition);
        }

        $parent = $this->makeUser('parent', [
            'name'  => 'رضا محمود',
            'email' => 'parent@altafawwuq.com',
            'phone' => '+97455000301',
        ]);

        return [$teachers, $students, $parent];
    }

    /** @return array<int, TeachingGroup> */
    private function seedTeaching(array $teachers, array $subjects, array $grades): array
    {
        // teacher email => [[subject key, grade key, private monthly, group specs], ...]
        $plan = [
            'ahmed@altafawwuq.com' => [
                ['الرياضيات', 'grade_12_science', 90000, [
                    ['name' => 'مجموعة الأحد والثلاثاء',  'price' => 45000, 'capacity' => 20, 'days' => [[0, '16:00', '17:30'], [2, '16:00', '17:30']]],
                    ['name' => 'مجموعة السبت المكثفة',    'price' => 55000, 'capacity' => 15, 'days' => [[6, '10:00', '12:00']]],
                ]],
                ['الرياضيات', 'grade_11_literary', 80000, [
                    ['name' => 'مجموعة الاثنين',          'price' => 40000, 'capacity' => 25, 'days' => [[1, '18:00', '19:30']]],
                ]],
            ],
            'sara@altafawwuq.com' => [
                ['الفيزياء', 'grade_12_science', 95000, [
                    ['name' => 'مجموعة الأربعاء',         'price' => 50000, 'capacity' => 18, 'days' => [[3, '17:00', '18:30']]],
                ]],
                ['الكيمياء', 'grade_11_science', 85000, [
                    ['name' => 'مجموعة الخميس',           'price' => 42000, 'capacity' => 20, 'days' => [[4, '16:00', '17:30']]],
                ]],
            ],
            'khaled@altafawwuq.com' => [
                ['الأحياء', 'grade_12_science', 75000, [
                    ['name' => 'مجموعة الثلاثاء',         'price' => 38000, 'capacity' => 22, 'days' => [[2, '19:00', '20:30']]],
                ]],
                ['اللغة العربية', 'grade_10', 60000, [
                    ['name' => 'مجموعة السبت',            'price' => 30000, 'capacity' => 30, 'days' => [[6, '14:00', '15:30']]],
                ]],
            ],
        ];

        $groups = [];

        foreach ($plan as $teacherEmail => $assignments) {
            $teacher = $teachers[$teacherEmail];

            foreach ($assignments as [$subjectKey, $gradeKey, $privatePrice, $groupSpecs]) {
                $assignment = TeachingAssignment::create([
                    'teacher_id'            => $teacher->id,
                    'subject_id'            => $subjects[$subjectKey]->id,
                    'grade_level_id'        => $grades[$gradeKey]->id,
                    'private_monthly_price' => $privatePrice,
                    'currency'              => 'QAR',
                    'accepts_private'       => true,
                    'is_active'             => true,
                ]);

                // A couple of open private slots so the booking page is not empty.
                foreach ([3, 5, 8] as $daysAhead) {
                    PrivateSessionSlot::create([
                        'teaching_assignment_id' => $assignment->id,
                        'starts_at'              => now()->addDays($daysAhead)->setTime(20, 0),
                        'ends_at'                => now()->addDays($daysAhead)->setTime(21, 0),
                        'timezone'               => 'Asia/Qatar',
                        'status'                 => 'available',
                    ]);
                }

                foreach ($groupSpecs as $spec) {
                    $firstDay = $spec['days'][0];

                    $group = TeachingGroup::create([
                        'teaching_assignment_id' => $assignment->id,
                        'name'                   => $spec['name'],
                        'capacity'               => $spec['capacity'],
                        'monthly_price'          => $spec['price'],
                        'currency'               => 'QAR',
                        'day_of_week'            => $firstDay[0],
                        'start_time'             => $firstDay[1],
                        'end_time'               => $firstDay[2],
                        'duration_minutes'       => $this->minutesBetween($firstDay[1], $firstDay[2]),
                        'timezone'               => 'Asia/Qatar',
                        'is_active'              => true,
                    ]);

                    foreach ($spec['days'] as [$day, $start, $end]) {
                        TeachingGroupSchedule::create([
                            'teaching_group_id' => $group->id,
                            'day_of_week'       => $day,
                            'start_time'        => $start,
                            'end_time'          => $end,
                            'duration_minutes'  => $this->minutesBetween($start, $end),
                        ]);
                    }

                    $groups[] = $group;
                }
            }
        }

        return $groups;
    }

    /** @param array<int, TeachingGroup> $groups */
    private function seedContent(array $groups): void
    {
        foreach ($groups as $group) {
            $materials = [];

            foreach (range(1, 4) as $order) {
                $materials[] = GroupMaterial::create([
                    'teaching_group_id' => $group->id,
                    'title'             => "الحصة {$order} — شرح مبسط",
                    'video_url'         => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    'duration_seconds'  => 60 * random_int(25, 55),
                    'order'             => $order,
                    // The first one is open so visitors can sample the teaching style.
                    'is_free_preview'   => $order === 1,
                    'description'       => 'شرح الدرس مع أمثلة محلولة من الامتحانات السابقة.',
                ]);
            }

            $quiz = Quiz::create([
                'teaching_group_id'  => $group->id,
                'lesson_id'          => $materials[0]->id,
                'title'              => 'اختبار قصير على الحصة الأولى',
                'passing_score'      => 60,
                'time_limit_minutes' => 15,
                'is_active'          => true,
            ]);

            foreach (range(1, 3) as $index) {
                $question = QuizQuestion::create([
                    'quiz_id'       => $quiz->id,
                    'question_text' => "السؤال {$index}: اختر الإجابة الصحيحة.",
                    'type'          => 'single',
                    'order'         => $index,
                ]);

                foreach (range(1, 4) as $optionIndex) {
                    QuizOption::create([
                        'question_id' => $question->id,
                        'option_text' => "الخيار {$optionIndex}",
                        'is_correct'  => $optionIndex === 1,
                    ]);
                }
            }

            Worksheet::create([
                'teaching_group_id'   => $group->id,
                'lesson_id'           => $materials[0]->id,
                'title'               => 'ورقة عمل — الحصة الأولى',
                'file_path'           => '/storage/worksheets/sample.pdf',
                'type'                => Worksheet::TYPE_HOMEWORK,
                'requires_submission' => true,
                'due_date'            => now()->addWeek()->toDateString(),
                'max_score'           => 20,
            ]);
        }
    }

    /**
     * @param array<int, User>          $students
     * @param array<int, TeachingGroup> $groups
     */
    private function seedSubscriptions(array $students, array $groups): void
    {
        $commissionPercent = 20;

        foreach ($students as $index => $student) {
            // Give each student two groups so dashboards have something to show.
            foreach ([$groups[$index % count($groups)], $groups[($index + 2) % count($groups)]] as $group) {
                $group->loadMissing('assignment');

                $existing = Subscription::where('student_id', $student->id)
                    ->where('teaching_group_id', $group->id)
                    ->exists();

                if ($existing) {
                    continue;
                }

                $periodStart = now()->startOfDay()->subDays(random_int(0, 20));

                $subscription = Subscription::create([
                    'student_id'             => $student->id,
                    'type'                   => Subscription::TYPE_GROUP,
                    'teaching_assignment_id' => $group->teaching_assignment_id,
                    'teaching_group_id'      => $group->id,
                    'monthly_price'          => $group->monthly_price,
                    'currency'               => 'QAR',
                    'period_start'           => $periodStart,
                    'period_end'             => $periodStart->copy()->addMonth(),
                    'status'                 => Subscription::STATUS_ACTIVE,
                ]);

                SessionBooking::create([
                    'student_id'        => $student->id,
                    'teaching_group_id' => $group->id,
                    'status'            => 'confirmed',
                    'booked_at'         => $periodStart,
                ]);

                $platformCut = (int) floor(($group->monthly_price * $commissionPercent) / 100);

                $payment = Payment::create([
                    'user_id'                    => $student->id,
                    'subscription_id'            => $subscription->id,
                    'amount'                     => $group->monthly_price,
                    'original_amount'            => $group->monthly_price,
                    'currency'                   => 'QAR',
                    'gateway'                    => 'stripe',
                    'gateway_ref'                => 'seed_' . $subscription->id,
                    'status'                     => Payment::STATUS_PAID,
                    'paid_at'                    => $periodStart,
                    'commission_percent'         => $commissionPercent,
                    'platform_commission_amount' => $platformCut,
                    'teacher_earnings'           => $group->monthly_price - $platformCut,
                ]);

                Invoice::create([
                    'payment_id'     => $payment->id,
                    'invoice_number' => 'INV-' . now()->year . '-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
                    'issued_at'      => $periodStart,
                ]);

                // Partial progress through the group's material.
                foreach ($group->materials()->get()->take(2) as $material) {
                    LessonProgress::create([
                        'student_id'      => $student->id,
                        'lesson_id'       => $material->id,
                        'watched_seconds' => $material->duration_seconds,
                        'is_completed'    => true,
                    ]);
                }
            }
        }

        Coupon::create([
            'code'             => 'WELCOME10',
            'discount_percent' => 10,
            'expires_at'       => now()->addMonths(3),
            'usage_limit'      => 100,
            'used_count'       => 0,
            'is_active'        => true,
        ]);
    }

    /**
     * @param array<string, User> $teachers
     * @param array<int, User>    $students
     */
    private function seedSocial(array $teachers, array $students, User $parent): void
    {
        $comments = [
            'شرح ممتاز وواضح، استفدت كتير.',
            'أسلوب بسيط وبيوصّل المعلومة بسرعة.',
            'أفضل معلم درست معه، متابعة مستمرة.',
        ];

        foreach (array_values($teachers) as $teacherIndex => $teacher) {
            foreach ($students as $studentIndex => $student) {
                // Not every student rates every teacher.
                if (($studentIndex + $teacherIndex) % 2 !== 0) {
                    continue;
                }

                TeacherReview::create([
                    'user_id'     => $student->id,
                    'teacher_id'  => $teacher->id,
                    'rating'      => random_int(4, 5),
                    'comment'     => $comments[$studentIndex % count($comments)],
                    'is_approved' => true,
                ]);
            }
        }

        ParentStudentLink::create([
            'parent_user_id'  => $parent->id,
            'student_user_id' => $students[0]->id,
            'relationship'    => 'father',
            'verified_at'     => now(),
        ]);
    }

    private function seedSettings(): void
    {
        $settings = [
            'platform_name'       => 'منصة التفوق',
            'contact_email'       => 'support@altafawwuq.com',
            'commission_percent'  => '20',
            'active_gateway'      => 'stripe',
            'site_theme'          => 'royal',
            'whatsapp_url'        => 'https://wa.me/97455000000',
            'footer_desc'         => 'منصة تعليمية قطرية تربط الطالب بأفضل المعلمين — اختر معلمك واحجز حصصك.',
            'home_hero_title'     => 'تفوّق في دراستك',
            'home_hero_subtitle'  => 'مع المعلم اللي يناسبك',
            'home_hero_desc'      => 'اختر صفك، ثم المادة، ثم شاهد المعلمين وطريقة شرح كل واحد فيهم — واحجز مع اللي يناسبك.',
            'home_stats_students' => '+500',
            'home_stats_courses'  => '+6',
            'home_stats_teachers' => '+20',
            'manual_payment_methods' => json_encode([
                ['type' => 'bank', 'name' => 'بنك قطر الوطني (QNB)', 'account_number' => 'QA00 QNBA 0000 0000 1234 5678'],
            ], JSON_UNESCAPED_UNICODE),
        ];

        foreach ($settings as $key => $value) {
            PlatformSetting::updateOrCreate(['key' => $key], ['value' => $value, 'type' => 'string']);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────

    private function makeUser(string $role, array $attributes): User
    {
        $user = User::create([
            'password'          => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'is_active'         => true,
            ...$attributes,
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function minutesBetween(string $start, string $end): int
    {
        return (int) round((strtotime($end) - strtotime($start)) / 60);
    }
}
