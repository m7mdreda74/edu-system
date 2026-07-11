<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\CourseLesson;
use App\Domain\Course\Models\Subject;
use App\Domain\Course\Models\GradeLevel;
use App\Domain\Course\Models\Worksheet;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\User\Models\User;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizQuestion;
use App\Domain\Quiz\Models\QuizOption;
use App\Domain\Payment\Models\Coupon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 1. Clean Database Tables ────────────────────────────────
        Schema::disableForeignKeyConstraints();
        DB::table('users')->truncate();
        DB::table('courses')->truncate();
        DB::table('course_lessons')->truncate();
        DB::table('subjects')->truncate();
        DB::table('enrollments')->truncate();
        DB::table('quizzes')->truncate();
        DB::table('quiz_questions')->truncate();
        DB::table('quiz_options')->truncate();
        DB::table('grade_levels')->truncate();
        DB::table('coupons')->truncate();
        DB::table('payments')->truncate();
        DB::table('platform_settings')->truncate();
        DB::table('worksheets')->truncate();
        DB::table('model_has_roles')->truncate();
        Schema::enableForeignKeyConstraints();

        // ─── 2. Create Roles ─────────────────────────────────────────
        $adminRole   = Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $studentRole = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $parentRole  = Role::firstOrCreate(['name' => 'parent',  'guard_name' => 'web']);

        // ─── 3. Seed Grade Levels (with Stage field) ──────────────────
        $gradeLevels = [
            // المرحلة الابتدائية (primary)
            ['key' => 'grade_1', 'name' => 'الصف الأول الابتدائي', 'name_en' => 'Grade 1', 'stage' => 'primary', 'is_active' => true],
            ['key' => 'grade_2', 'name' => 'الصف الثاني الابتدائي', 'name_en' => 'Grade 2', 'stage' => 'primary', 'is_active' => true],
            ['key' => 'grade_3', 'name' => 'الصف الثالث الابتدائي', 'name_en' => 'Grade 3', 'stage' => 'primary', 'is_active' => true],
            ['key' => 'grade_4', 'name' => 'الصف الرابع الابتدائي', 'name_en' => 'Grade 4', 'stage' => 'primary', 'is_active' => true],
            ['key' => 'grade_5', 'name' => 'الصف الخامس الابتدائي', 'name_en' => 'Grade 5', 'stage' => 'primary', 'is_active' => true],
            ['key' => 'grade_6', 'name' => 'الصف السادس الابتدائي', 'name_en' => 'Grade 6', 'stage' => 'primary', 'is_active' => true],

            // المرحلة الإعدادية (preparatory)
            ['key' => 'grade_7', 'name' => 'الصف السابع الإعدادي', 'name_en' => 'Grade 7', 'stage' => 'preparatory', 'is_active' => true],
            ['key' => 'grade_8', 'name' => 'الصف الثامن الإعدادي', 'name_en' => 'Grade 8', 'stage' => 'preparatory', 'is_active' => true],
            ['key' => 'grade_9', 'name' => 'الصف التاسع الإعدادي', 'name_en' => 'Grade 9', 'stage' => 'preparatory', 'is_active' => true],

            // المرحلة الثانوية (secondary)
            ['key' => 'grade_10', 'name' => 'الصف العاشر الثانوي', 'name_en' => 'Grade 10', 'stage' => 'secondary', 'is_active' => true],
            ['key' => 'grade_11', 'name' => 'الصف الحادي عشر الثانوي', 'name_en' => 'Grade 11', 'stage' => 'secondary', 'is_active' => true],
            ['key' => 'grade_12', 'name' => 'الصف الثاني عشر الثانوي', 'name_en' => 'Grade 12', 'stage' => 'secondary', 'is_active' => true],

            // عام / كل المراحل (all)
            ['key' => 'all', 'name' => 'كل الصفوف والمراحل', 'name_en' => 'All Grades', 'stage' => 'all', 'is_active' => true],
        ];

        foreach ($gradeLevels as $gl) {
            GradeLevel::create($gl);
        }

        // ─── 4. Seed Subjects ────────────────────────────────────────
        $subjects = [
            ['name' => 'الرياضيات',  'name_en' => 'Mathematics', 'grade_level' => 'all',      'icon' => 'calculator'],
            ['name' => 'العلوم العامة', 'name_en' => 'General Science', 'grade_level' => 'grade_5',  'icon' => 'flask'],
            ['name' => 'الفيزياء',   'name_en' => 'Physics',     'grade_level' => 'grade_11', 'icon' => 'atom'],
            ['name' => 'الكيمياء',   'name_en' => 'Chemistry',   'grade_level' => 'grade_11', 'icon' => 'flask'],
            ['name' => 'الأحياء',    'name_en' => 'Biology',     'grade_level' => 'grade_11', 'icon' => 'dna'],
            ['name' => 'اللغة العربية','name_en' => 'Arabic',    'grade_level' => 'all',      'icon' => 'book'],
            ['name' => 'اللغة الإنجليزية','name_en' => 'English','grade_level' => 'all',     'icon' => 'language'],
            ['name' => 'الدراسات الاجتماعية', 'name_en' => 'Social Studies', 'grade_level' => 'grade_8', 'icon' => 'landmark'],
        ];

        $subjectModels = [];
        foreach ($subjects as $s) {
            $subjectModels[] = Subject::create($s);
        }

        // ─── 5. Admin User ───────────────────────────────────────────
        $admin = User::create([
            'name'              => 'مدير المنصة',
            'email'             => 'admin@altafawwuq.com',
            'password'          => Hash::make('password'),
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole($adminRole);

        // ─── 6. Teachers ─────────────────────────────────────────────
        $teachersData = [
            ['name' => 'أ. محمد الأحمد',  'email' => 'teacher1@altafawwuq.com', 'bio' => 'معلم رياضيات بخبرة 10 سنوات'],
            ['name' => 'أ. سارة العمري',   'email' => 'teacher2@altafawwuq.com', 'bio' => 'معلمة فيزياء وكيمياء للثانوية العامة'],
            ['name' => 'أ. خالد الزهراني', 'email' => 'teacher3@altafawwuq.com', 'bio' => 'معلم لغة عربية وعلوم للمراحل التأسيسية'],
        ];

        $teacherUsers = [];
        foreach ($teachersData as $index => $t) {
            $avatarPath = $this->generateSeededImage("Teacher " . ($index + 1), 'avatars', 150, 150, '#7A1C37');
            $teacher = User::create([
                'name'              => $t['name'],
                'email'             => $t['email'],
                'bio'               => $t['bio'],
                'avatar'            => $avatarPath,
                'password'          => Hash::make('password'),
                'is_active'         => true,
                'email_verified_at' => now(),
            ]);
            $teacher->assignRole($teacherRole);
            $teacherUsers[] = $teacher;
        }

        // ─── 7. Students ─────────────────────────────────────────────
        $studentsData = [
            ['name' => 'أحمد محمود (ثانوي)',  'email' => 'student1@altafawwuq.com',  'grade' => 'grade_11'],
            ['name' => 'فاطمة علي (ابتدائي)', 'email' => 'student2@altafawwuq.com',  'grade' => 'grade_5'],
            ['name' => 'يوسف إبراهيم (إعدادي)','email' => 'student3@altafawwuq.com',  'grade' => 'grade_8'],
        ];

        $studentUsers = [];
        foreach ($studentsData as $studentData) {
            $student = User::create([
                'name'              => $studentData['name'],
                'email'             => $studentData['email'],
                'grade_level'       => $studentData['grade'],
                'password'          => Hash::make('password'),
                'is_active'         => true,
                'email_verified_at' => now(),
            ]);
            $student->assignRole($studentRole);
            $studentUsers[] = $student;
        }

        // ─── 8. Courses (Paid and Free across stages) ─────────────────
        $coursesData = [
            // الابتدائية (primary)
            [
                'title'          => 'العلوم للمرحلة الابتدائية — الصف الخامس',
                'slug'           => 'primary-science-grade5',
                'description'    => 'شرح مبسط وممتع لمادة العلوم للصف الخامس الابتدائي يغطي النباتات والحيوانات والنظام البيئي وتنوع الحياة.',
                'price'          => 8900,
                'discount_price' => 5900,
                'grade_level'    => 'grade_5',
                'level'          => 'beginner',
                'teacher_idx'    => 2, // خالد الزهراني
                'subject_idx'    => 1, // العلوم العامة
            ],
            [
                'title'          => 'اللغة العربية وقواعد النحو للمرحلة الابتدائية',
                'slug'           => 'arabic-grammar-primary',
                'description'    => 'تأسيس شامل وممتاز في قواعد النحو والإملاء والقراءة لصفوف المرحلة الابتدائية بأسلوب تفاعلي.',
                'price'          => 6000,
                'discount_price' => 4500,
                'grade_level'    => 'grade_4',
                'level'          => 'beginner',
                'teacher_idx'    => 2,
                'subject_idx'    => 5, // اللغة العربية
            ],
            [
                'title'          => 'اللغة الإنجليزية الأساسية للمبتدئين',
                'slug'           => 'english-basic-primary',
                'description'    => 'شرح القواعد الأساسية والمفردات اللغوية والمحادثة البسيطة لصفوف المرحلة الابتدائية.',
                'price'          => 5000,
                'discount_price' => null,
                'grade_level'    => 'grade_3',
                'level'          => 'beginner',
                'teacher_idx'    => 2,
                'subject_idx'    => 6, // اللغة الإنجليزية
            ],
            // الإعدادية (preparatory)
            [
                'title'          => 'الدراسات الاجتماعية للصف الثامن — الجغرافيا والتاريخ',
                'slug'           => 'preparatory-social-grade8',
                'description'    => 'كورس متكامل لجميع دروس الدراسات الاجتماعية والجغرافيا والتاريخ للصف الثامن مع ملخصات وافية وامتحانات تدريبية.',
                'price'          => 0, // مجاني للتجربة والالتحاق المباشر!
                'discount_price' => null,
                'grade_level'    => 'grade_8',
                'level'          => 'beginner',
                'teacher_idx'    => 2,
                'subject_idx'    => 7, // الدراسات الاجتماعية
            ],
            [
                'title'          => 'اللغة العربية وبلاغتها للمرحلة الإعدادية',
                'slug'           => 'arabic-rhetoric-preparatory',
                'description'    => 'شرح مبسط لقواعد النحو المتقدمة، الصرف، وتأسيس البلاغة لصفوف المرحلة الإعدادية.',
                'price'          => 8000,
                'discount_price' => 6000,
                'grade_level'    => 'grade_8',
                'level'          => 'intermediate',
                'teacher_idx'    => 2,
                'subject_idx'    => 5, // اللغة العربية
            ],
            // الثانوية (secondary)
            [
                'title'          => 'الرياضيات للثانوية العامة — التفاضل والتكامل',
                'slug'           => 'math-grade12-calculus',
                'description'    => 'كورس تفصيلي للثانوية العامة يغطي النهايات والمشتقات والتكامل وتطبيقاتها الهندسية والفيزيائية مع حل نماذج الامتحانات النهائية.',
                'price'          => 19900,
                'discount_price' => 14900,
                'grade_level'    => 'grade_12',
                'level'          => 'advanced',
                'teacher_idx'    => 0, // محمد الأحمد
                'subject_idx'    => 0, // الرياضيات
            ],
            [
                'title'          => 'الفيزياء الحديثة للثانوية العامة',
                'slug'           => 'secondary-physics-modern',
                'description'    => 'تبسيط مفاهيم الكهرباء والمغناطيسية والفيزياء النووية والذرية لطلاب الصف الحادي عشر والثاني عشر.',
                'price'          => 15000,
                'discount_price' => null,
                'grade_level'    => 'grade_11',
                'level'          => 'intermediate',
                'teacher_idx'    => 1, // سارة العمري
                'subject_idx'    => 2, // الفيزياء
            ],
            [
                'title'          => 'الكيمياء العضوية المبسطة — الصف الحادي عشر',
                'slug'           => 'chemistry-organic-grade11',
                'description'    => 'شرح شامل وممتع لأسس الكيمياء العضوية، التفاعلات الكيميائية، والمعادلات لطلاب الصف الحادي عشر.',
                'price'          => 12000,
                'discount_price' => 9000,
                'grade_level'    => 'grade_11',
                'level'          => 'intermediate',
                'teacher_idx'    => 1,
                'subject_idx'    => 3, // الكيمياء
            ],
            [
                'title'          => 'علم الأحياء الخلوي والوراثة للثانوية',
                'slug'           => 'biology-cellular-genetics',
                'description'    => 'شرح تفصيلي للتركيب الخلوي، الحمض النووي، وقوانين الوراثة لطلاب المرحلة الثانوية.',
                'price'          => 13000,
                'discount_price' => 10000,
                'grade_level'    => 'grade_11',
                'level'          => 'intermediate',
                'teacher_idx'    => 1,
                'subject_idx'    => 4, // الأحياء
            ],
            [
                'title'          => 'مهارات اللغة الإنجليزية للثانوية العامة',
                'slug'           => 'english-skills-secondary',
                'description'    => 'تدريب عملي على مهارات الكتابة، القواعد المتقدمة، وفهم النصوص للامتحانات النهائية للثانوية العامة.',
                'price'          => 11000,
                'discount_price' => 8500,
                'grade_level'    => 'grade_12',
                'level'          => 'advanced',
                'teacher_idx'    => 0,
                'subject_idx'    => 6, // اللغة الإنجليزية
            ],
        ];

        $courseModels = [];
        foreach ($coursesData as $c) {
            $thumbnailPath = $this->generateSeededImage($c['slug'], 'thumbnails', 640, 360, '#7A1C37');
            $courseModels[] = Course::create([
                'teacher_id'     => $teacherUsers[$c['teacher_idx']]->id,
                'subject_id'     => $subjectModels[$c['subject_idx']]->id,
                'title'          => $c['title'],
                'slug'           => $c['slug'],
                'description'    => $c['description'],
                'price'          => $c['price'],
                'discount_price' => $c['discount_price'],
                'grade_level'    => $c['grade_level'],
                'level'          => $c['level'],
                'thumbnail'      => $thumbnailPath,
                'is_published'   => true,
            ]);
        }

        // ─── 9. Lessons (4 lessons per course) ────────────────────────
        foreach ($courseModels as $course) {
            $lessons = [
                ['title' => 'الدرس الأول: مقدمة ومفاهيم عامة تأسيسية', 'is_free' => true],
                ['title' => 'الدرس الثاني: تفصيل الشرح النظري والأمثلة', 'is_free' => false],
                ['title' => 'الدرس الثالث: تطبيقات وتمارين تفاعلية عملية', 'is_free' => false],
                ['title' => 'الدرس الرابع: مراجعة وحل أسئلة دورات سابقة', 'is_free' => false],
            ];
            foreach ($lessons as $index => $l) {
                CourseLesson::create([
                    'course_id'        => $course->id,
                    'title'            => $l['title'],
                    'video_url'        => 'https://example.com/videos/seeded-video.mp4',
                    'duration_seconds' => rand(1200, 3600),
                    'order'            => $index + 1,
                    'is_free_preview'  => $l['is_free'],
                ]);
            }
        }

        // ─── 10. Quizzes & Options ────────────────────────────────────
        foreach ($courseModels as $course) {
            $quiz = Quiz::create([
                'course_id'          => $course->id,
                'title'              => "الاختبار التقيمي الأول لـ {$course->title}",
                'time_limit_minutes' => 15,
                'passing_score'      => 60,
                'is_active'          => true,
            ]);

            $questions = [
                [
                    'question_text' => 'السؤال الأول: ما هي الوحدة الأساسية لهذا المنهج الدراسي؟',
                    'options' => ['الخيار الأول (الإجابة الصحيحة)', 'الخيار الثاني الخطأ', 'الخيار الثالث الخطأ', 'الخيار الرابع الخطأ'],
                    'correct_idx' => 0,
                ],
                [
                    'question_text' => 'السؤال الثاني: ما هي النتيجة المتوقعة للتطبيقات العملية في الكورس؟',
                    'options' => ['الخيار الأول خطأ', 'الخيار الثاني (الإجابة الصحيحة)', 'الخيار الثالث خطأ'],
                    'correct_idx' => 1,
                ],
            ];

            foreach ($questions as $qData) {
                $question = QuizQuestion::create([
                    'quiz_id'       => $quiz->id,
                    'question_text' => $qData['question_text'],
                    'type'          => 'single',
                ]);

                foreach ($qData['options'] as $idx => $optText) {
                    QuizOption::create([
                        'question_id' => $question->id,
                        'option_text' => $optText,
                        'is_correct'  => $idx === $qData['correct_idx'],
                    ]);
                }
            }
        }

        // ─── 11. Worksheets ──────────────────────────────────────────
        foreach ($courseModels as $course) {
            Worksheet::create([
                'course_id'   => $course->id,
                'title'       => "واجب منزلي وتدريب لـ {$course->title}",
                'file_path'   => '/storage/seeded-worksheets/assignment.pdf',
                'type'        => 'homework',
                'requires_submission' => true,
            ]);
        }

        // ─── 12. Enrollments & Progress ──────────────────────────────
        // 1. student1 (secondary) enrolled in Math grade 12 (paid)
        $e1 = Enrollment::create([
            'user_id'          => $studentUsers[0]->id,
            'course_id'        => $courseModels[2]->id,
            'progress_percent' => 50,
            'enrolled_at'      => now()->subDays(10),
        ]);
        // Watched lessons
        \App\Domain\Enrollment\Models\LessonProgress::create([
            'enrollment_id'   => $e1->id,
            'lesson_id'       => $courseModels[2]->lessons->first()->id,
            'watched_seconds' => 300,
            'is_completed'    => true,
        ]);

        // 2. student2 (primary) enrolled in Science grade 5 (paid) -> Completed!
        $e2 = Enrollment::create([
            'user_id'          => $studentUsers[1]->id,
            'course_id'        => $courseModels[0]->id,
            'progress_percent' => 100,
            'enrolled_at'      => now()->subDays(5),
            'completed_at'     => now()->subDays(1),
        ]);
        foreach ($courseModels[0]->lessons as $l) {
            \App\Domain\Enrollment\Models\LessonProgress::create([
                'enrollment_id'   => $e2->id,
                'lesson_id'       => $l->id,
                'watched_seconds' => $l->duration_seconds,
                'is_completed'    => true,
            ]);
        }

        // 3. student3 (preparatory) enrolled in Social Studies (free)
        $e3 = Enrollment::create([
            'user_id'          => $studentUsers[2]->id,
            'course_id'        => $courseModels[1]->id,
            'progress_percent' => 0,
            'enrolled_at'      => now()->subDays(1),
        ]);

        // ─── 13. Coupons ─────────────────────────────────────────────
        Coupon::create([
            'code'             => 'TAFAWWUQ10',
            'discount_percent' => 10,
            'is_active'        => true,
            'usage_limit'      => 100,
            'used_count'       => 0,
        ]);
        Coupon::create([
            'code'             => 'FREE100',
            'discount_percent' => 100,
            'is_active'        => true,
            'usage_limit'      => 10,
            'used_count'       => 0,
        ]);

        // ─── 14. Platform Settings ────────────────────────────────────
        DB::table('platform_settings')->insert([
            ['key' => 'commission_percent', 'value' => '20', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'platform_name',      'value' => 'منصة التفوق', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'platform_email',     'value' => 'support@altafawwuq.com', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->command->info('✅ Database seeded successfully with stages & rich dummy records!');
        $this->command->info('   Admin:   admin@altafawwuq.com / password');
        $this->command->info('   Teacher: teacher1@altafawwuq.com / password');
        $this->command->info('   Student: student1@altafawwuq.com / password');
    }

    /**
     * Generate a simple WebP image using GD library and save it.
     */
    private function generateSeededImage(string $text, string $folder, int $width, int $height, string $bgColorHex): string
    {
        $dir = storage_path('app/public/' . $folder);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $image = imagecreatetruecolor($width, $height);

        // Gradient from Deep Burgundy (#46091B) to Dark Gold (#604618)
        $startR = 70;  $startG = 9;   $startB = 27;
        $endR   = 96;  $endG   = 70;  $endB   = 24;

        for ($x = 0; $x < $width; $x++) {
            $r = intval($startR + ($endR - $startR) * ($x / $width));
            $g = intval($startG + ($endG - $startG) * ($x / $width));
            $b = intval($startB + ($endB - $startB) * ($x / $width));
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, $x, 0, $x, $height, $color);
        }

        // Draw soft glowing ambient circles with alpha transparency
        imagealphablending($image, true);
        
        // Aura 1: Gold glow top-right
        $goldGlow = imagecolorallocatealpha($image, 197, 160, 57, 105);
        imagefilledellipse($image, intval($width * 0.85), intval($height * 0.2), 220, 220, $goldGlow);

        // Aura 2: Burgundy glow bottom-left
        $burgundyGlow = imagecolorallocatealpha($image, 122, 28, 55, 100);
        imagefilledellipse($image, intval($width * 0.15), intval($height * 0.8), 280, 280, $burgundyGlow);

        // Aura 3: Subtle center gold glow
        $centerGlow = imagecolorallocatealpha($image, 212, 175, 55, 115);
        imagefilledellipse($image, intval($width / 2), intval($height / 2), 150, 150, $centerGlow);

        $filename = uniqid() . '.webp';
        $path = $dir . '/' . $filename;
        imagewebp($image, $path, 90);
        imagedestroy($image);

        return '/storage/' . $folder . '/' . $filename;
    }
}
