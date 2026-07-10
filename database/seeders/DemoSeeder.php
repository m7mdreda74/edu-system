<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\CourseLesson;
use App\Domain\Course\Models\Subject;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Payment\Models\Coupon;
use App\Domain\Payment\Models\Payment;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizOption;
use App\Domain\Quiz\Models\QuizQuestion;
use App\Domain\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Subjects ───────────────────────────────────────────────
        $subjects = [
            ['name' => 'رياضيات',      'icon' => '➗', 'is_active' => true],
            ['name' => 'فيزياء',        'icon' => '⚡', 'is_active' => true],
            ['name' => 'كيمياء',        'icon' => '🧪', 'is_active' => true],
            ['name' => 'أحياء',         'icon' => '🌱', 'is_active' => true],
            ['name' => 'لغة عربية',    'icon' => '📖', 'is_active' => true],
            ['name' => 'لغة إنجليزية', 'icon' => '🌍', 'is_active' => true],
        ];

        foreach ($subjects as $s) {
            Subject::firstOrCreate(['name' => $s['name']], $s);
        }

        $math    = Subject::where('name', 'رياضيات')->first();
        $physics = Subject::where('name', 'فيزياء')->first();
        $chem    = Subject::where('name', 'كيمياء')->first();

        // ─── Admin User ─────────────────────────────────────────────
        $admin = User::firstOrCreate(['email' => 'admin@altafawwuq.com'], [
            'name'      => 'مدير المنصة',
            'password'  => Hash::make('password123'),
            'is_active' => true,
        ]);
        $admin->syncRoles(['admin']);

        // ─── Teachers ────────────────────────────────────────────────
        $teacher1 = User::firstOrCreate(['email' => 'teacher1@altafawwuq.com'], [
            'name'      => 'أ. أحمد محمد',
            'password'  => Hash::make('password123'),
            'bio'       => 'معلم رياضيات بخبرة 10 سنوات، تخصص في التفاضل والتكامل.',
            'is_active' => true,
        ]);
        $teacher1->syncRoles(['teacher']);

        $teacher2 = User::firstOrCreate(['email' => 'teacher2@altafawwuq.com'], [
            'name'      => 'د. سارة العلي',
            'password'  => Hash::make('password123'),
            'bio'       => 'دكتوراه في الفيزياء النظرية، تدرس في أفضل المدارس.',
            'is_active' => true,
        ]);
        $teacher2->syncRoles(['teacher']);

        // ─── Students ────────────────────────────────────────────────
        $students = [];
        foreach ([
            ['email' => 'student1@test.com', 'name' => 'محمد عبدالله', 'grade' => 'grade_12'],
            ['email' => 'student2@test.com', 'name' => 'فاطمة الزهراء', 'grade' => 'grade_11'],
            ['email' => 'student3@test.com', 'name' => 'خالد السعيد',   'grade' => 'grade_12'],
        ] as $s) {
            $u = User::firstOrCreate(['email' => $s['email']], [
                'name'        => $s['name'],
                'password'    => Hash::make('password123'),
                'grade_level' => $s['grade'],
                'is_active'   => true,
            ]);
            $u->syncRoles(['student']);
            $students[] = $u;
        }

        // ─── Courses ─────────────────────────────────────────────────
        $courses = [
            [
                'teacher_id'  => $teacher1->id,
                'subject_id'  => $math->id,
                'title'       => 'رياضيات الصف الثاني عشر — التفاضل والتكامل',
                'slug'        => 'math-grade12-derivatives',
                'description' => 'كورس شامل في التفاضل والتكامل يغطي جميع مواضيع الثانوية العامة. شامل لشرح مفصّل وتمارين وحلول.',
                'price'       => 0,
                'grade_level' => 'grade_12',
                'level'       => 'intermediate',
                'is_published'=> true,
            ],
            [
                'teacher_id'  => $teacher2->id,
                'subject_id'  => $physics->id,
                'title'       => 'فيزياء الصف الحادي عشر — الحركة والديناميكا',
                'slug'        => 'physics-grade11-dynamics',
                'description' => 'دراسة شاملة لقوانين نيوتن والحركة، مع تطبيقات عملية وأمثلة من الحياة اليومية.',
                'price'       => 50_000,  // 500 QAR
                'grade_level' => 'grade_11',
                'level'       => 'beginner',
                'is_published'=> true,
            ],
            [
                'teacher_id'  => $teacher1->id,
                'subject_id'  => $chem->id,
                'title'       => 'كيمياء الصف العاشر — مدخل إلى عالم الجزيئات',
                'slug'        => 'chemistry-grade10-intro',
                'description' => 'تعرّف على مبادئ الكيمياء: الذرة، العناصر، التفاعلات، بأسلوب ممتع وبسيط.',
                'price'       => 25_000,  // 250 QAR
                'discount_price' => 15_000, // 150 QAR (خصم 40%)
                'grade_level' => 'grade_10',
                'level'       => 'beginner',
                'is_published'=> true,
            ],
        ];

        foreach ($courses as $courseData) {
            $course = Course::firstOrCreate(['slug' => $courseData['slug']], $courseData);

            // Add sample lessons to each course
            if ($course->wasRecentlyCreated && $course->total_lessons === 0) {
                $lessonCount = 0;
                foreach ([
                    ['مقدمة ونظرة عامة على الكورس', 300, true],
                    ['الدرس الأول: المفاهيم الأساسية', 2700, false],
                    ['الدرس الثاني: تطبيقات عملية', 3600, false],
                    ['الدرس الثالث: مسائل متقدمة', 4500, false],
                    ['مراجعة شاملة وامتحان تجريبي', 1800, false],
                ] as $idx => [$title, $duration, $isFree]) {
                    CourseLesson::create([
                        'course_id'        => $course->id,
                        'title'            => $title,
                        'video_url'        => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'duration_seconds' => $duration,
                        'order'            => $idx + 1,
                        'is_free_preview'  => $isFree,
                    ]);
                    $lessonCount++;
                }
                $course->update([
                    'total_lessons'  => $lessonCount,
                    'total_duration' => 12900,
                ]);
            }

            // Add a quiz to each course
            $quiz = Quiz::firstOrCreate(
                ['course_id' => $course->id, 'title' => 'اختبار ' . $course->title],
                [
                    'passing_score'      => 70,
                    'time_limit_minutes' => 30,
                    'is_active'          => true,
                ]
            );

            if ($quiz->wasRecentlyCreated) {
                foreach ([
                    ['ما هو الهدف الرئيسي من هذا الكورس؟', 'فهم المفاهيم الأساسية'],
                    ['كم عدد الدروس في الكورس؟', '5 دروس'],
                    ['ما الدرجة المطلوبة للنجاح؟', '70%'],
                ] as $qIdx => [$qText, $correctAnswer]) {
                    $question = QuizQuestion::create([
                        'quiz_id'       => $quiz->id,
                        'question_text' => $qText,
                        'type'          => 'single',
                        'order'         => $qIdx + 1,
                    ]);
                    QuizOption::create(['question_id' => $question->id, 'option_text' => $correctAnswer,     'is_correct' => true]);
                    QuizOption::create(['question_id' => $question->id, 'option_text' => 'إجابة خاطئة أ',   'is_correct' => false]);
                    QuizOption::create(['question_id' => $question->id, 'option_text' => 'إجابة خاطئة ب',   'is_correct' => false]);
                    QuizOption::create(['question_id' => $question->id, 'option_text' => 'إجابة خاطئة ج',   'is_correct' => false]);
                }
            }
        }

        // ─── Free Enrollments ─────────────────────────────────────────
        $freeCourse = Course::where('slug', 'math-grade12-derivatives')->first();
        foreach ($students as $student) {
            Enrollment::firstOrCreate(
                ['user_id' => $student->id, 'course_id' => $freeCourse->id],
                ['progress_percent' => rand(0, 80), 'enrolled_at' => now()]
            );
        }

        // ─── Coupons ─────────────────────────────────────────────────
        Coupon::firstOrCreate(['code' => 'TAFAWWUQ20'], [
            'discount_percent' => 20,
            'expires_at'       => now()->addMonths(3),
            'usage_limit'      => 100,
            'used_count'       => 0,
            'is_active'        => true,
        ]);

        Coupon::firstOrCreate(['code' => 'STUDENT50'], [
            'discount_percent' => 50,
            'expires_at'       => now()->addMonth(),
            'usage_limit'      => 20,
            'used_count'       => 0,
            'is_active'        => true,
        ]);

        $this->command->info('✅ Demo data seeded successfully!');
        $this->command->table(
            ['الدور', 'الإيميل', 'كلمة المرور'],
            [
                ['مدير',   'admin@altafawwuq.com',    'password123'],
                ['مدرس 1', 'teacher1@altafawwuq.com', 'password123'],
                ['مدرس 2', 'teacher2@altafawwuq.com', 'password123'],
                ['طالب 1', 'student1@test.com',        'password123'],
                ['طالب 2', 'student2@test.com',        'password123'],
                ['كوبون',  'TAFAWWUQ20',               'خصم 20%'],
            ]
        );
    }
}
