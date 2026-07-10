<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\CourseLesson;
use App\Domain\Course\Models\Subject;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 1. Create Roles ─────────────────────────────────────────
        $adminRole   = Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $studentRole = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $parentRole  = Role::firstOrCreate(['name' => 'parent',  'guard_name' => 'web']);

        // ─── 2. Admin User ───────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@altafawwuq.com'],
            [
                'name'              => 'مدير المنصة',
                'password'          => Hash::make('password'),
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole($adminRole);

        // ─── 3. Teachers ─────────────────────────────────────────────
        $teachers = [
            ['name' => 'أ. محمد الأحمد',  'email' => 'teacher1@altafawwuq.com', 'bio' => 'معلم رياضيات بخبرة 10 سنوات'],
            ['name' => 'أ. سارة العمري',   'email' => 'teacher2@altafawwuq.com', 'bio' => 'معلمة فيزياء وكيمياء'],
            ['name' => 'أ. خالد الزهراني', 'email' => 'teacher3@altafawwuq.com', 'bio' => 'معلم لغة عربية وأدب'],
            ['name' => 'أ. نور الدوسري',   'email' => 'teacher4@altafawwuq.com', 'bio' => 'معلمة أحياء وعلوم'],
            ['name' => 'أ. عمر الشمري',    'email' => 'teacher5@altafawwuq.com', 'bio' => 'معلم تاريخ وجغرافيا'],
        ];

        $teacherUsers = [];
        foreach ($teachers as $teacherData) {
            $teacher = User::firstOrCreate(
                ['email' => $teacherData['email']],
                [
                    'name'              => $teacherData['name'],
                    'bio'               => $teacherData['bio'],
                    'password'          => Hash::make('password'),
                    'is_active'         => true,
                    'email_verified_at' => now(),
                ]
            );
            $teacher->assignRole($teacherRole);
            $teacherUsers[] = $teacher;
        }

        // ─── 4. Students ─────────────────────────────────────────────
        $studentsData = [
            ['name' => 'أحمد محمود',    'email' => 'student1@altafawwuq.com',  'grade' => 'grade_11'],
            ['name' => 'فاطمة علي',     'email' => 'student2@altafawwuq.com',  'grade' => 'grade_12'],
            ['name' => 'يوسف إبراهيم',  'email' => 'student3@altafawwuq.com',  'grade' => 'grade_10'],
            ['name' => 'مريم سالم',     'email' => 'student4@altafawwuq.com',  'grade' => 'grade_11'],
            ['name' => 'عبدالله حسن',   'email' => 'student5@altafawwuq.com',  'grade' => 'grade_12'],
            ['name' => 'نوف الرشيد',    'email' => 'student6@altafawwuq.com',  'grade' => 'grade_10'],
            ['name' => 'طارق عبدالعزيز','email' => 'student7@altafawwuq.com',  'grade' => 'grade_11'],
            ['name' => 'لمياء القحطاني','email' => 'student8@altafawwuq.com',  'grade' => 'grade_12'],
            ['name' => 'ماجد الغامدي',  'email' => 'student9@altafawwuq.com',  'grade' => 'grade_10'],
            ['name' => 'ريم العتيبي',   'email' => 'student10@altafawwuq.com', 'grade' => 'grade_11'],
            ['name' => 'سلطان المطيري', 'email' => 'student11@altafawwuq.com', 'grade' => 'grade_12'],
            ['name' => 'هدى الشهري',    'email' => 'student12@altafawwuq.com', 'grade' => 'grade_10'],
            ['name' => 'وليد الحربي',   'email' => 'student13@altafawwuq.com', 'grade' => 'grade_11'],
            ['name' => 'أميرة الجهني',  'email' => 'student14@altafawwuq.com', 'grade' => 'grade_12'],
            ['name' => 'ناصر البلوي',   'email' => 'student15@altafawwuq.com', 'grade' => 'grade_10'],
            ['name' => 'دانا الزهراني', 'email' => 'student16@altafawwuq.com', 'grade' => 'grade_11'],
            ['name' => 'فهد العنزي',    'email' => 'student17@altafawwuq.com', 'grade' => 'grade_12'],
            ['name' => 'غدير المالكي',  'email' => 'student18@altafawwuq.com', 'grade' => 'grade_10'],
            ['name' => 'تركي الدوسري',  'email' => 'student19@altafawwuq.com', 'grade' => 'grade_11'],
            ['name' => 'شهد العمري',    'email' => 'student20@altafawwuq.com', 'grade' => 'grade_12'],
        ];

        $studentUsers = [];
        foreach ($studentsData as $studentData) {
            $student = User::firstOrCreate(
                ['email' => $studentData['email']],
                [
                    'name'              => $studentData['name'],
                    'grade_level'       => $studentData['grade'],
                    'password'          => Hash::make('password'),
                    'is_active'         => true,
                    'email_verified_at' => now(),
                ]
            );
            $student->assignRole($studentRole);
            $studentUsers[] = $student;
        }

        // ─── 5. Subjects ─────────────────────────────────────────────
        $subjects = [
            ['name' => 'الرياضيات',  'name_en' => 'Mathematics', 'grade_level' => 'all',      'icon' => 'calculator'],
            ['name' => 'الفيزياء',   'name_en' => 'Physics',     'grade_level' => 'grade_11',  'icon' => 'atom'],
            ['name' => 'الكيمياء',   'name_en' => 'Chemistry',   'grade_level' => 'grade_11',  'icon' => 'flask'],
            ['name' => 'الأحياء',    'name_en' => 'Biology',     'grade_level' => 'grade_11',  'icon' => 'dna'],
            ['name' => 'اللغة العربية','name_en' => 'Arabic',    'grade_level' => 'all',       'icon' => 'book'],
            ['name' => 'اللغة الإنجليزية','name_en' => 'English','grade_level' => 'all',      'icon' => 'language'],
            ['name' => 'التاريخ',    'name_en' => 'History',     'grade_level' => 'grade_10',  'icon' => 'landmark'],
            ['name' => 'الجغرافيا',  'name_en' => 'Geography',   'grade_level' => 'grade_10',  'icon' => 'globe'],
        ];

        $subjectModels = [];
        foreach ($subjects as $subjectData) {
            $subjectModels[] = Subject::firstOrCreate(
                ['name' => $subjectData['name']],
                $subjectData
            );
        }

        // ─── 6. Courses (10 courses) ──────────────────────────────────
        $coursesData = [
            [
                'title'          => 'الرياضيات للصف الثاني عشر — الدالة والمشتقات',
                'slug'           => 'math-grade12-derivatives',
                'description'    => 'كورس شامل يغطي الدالة والمشتقات والتفاضل لطلاب الصف الثاني عشر مع حل نماذج امتحانية',
                'price'          => 14900,  // 149 QAR in halala
                'discount_price' => 9900,   // 99 QAR
                'grade_level'    => 'grade_12',
                'level'          => 'intermediate',
                'teacher_idx'    => 0,
                'subject_idx'    => 0,
            ],
            [
                'title'          => 'الفيزياء الحديثة — الكهرباء والمغناطيسية',
                'slug'           => 'physics-electricity-magnetism',
                'description'    => 'شرح متكامل لوحدة الكهرباء والمغناطيسية مع تجارب عملية مصورة',
                'price'          => 19900,
                'discount_price' => null,
                'grade_level'    => 'grade_11',
                'level'          => 'intermediate',
                'teacher_idx'    => 1,
                'subject_idx'    => 1,
            ],
            [
                'title'          => 'الكيمياء العضوية من الصفر',
                'slug'           => 'organic-chemistry-basics',
                'description'    => 'أسهل طريقة لفهم الكيمياء العضوية مع أمثلة تطبيقية ومسائل محلولة',
                'price'          => 17900,
                'discount_price' => 12900,
                'grade_level'    => 'grade_11',
                'level'          => 'beginner',
                'teacher_idx'    => 1,
                'subject_idx'    => 2,
            ],
            [
                'title'          => 'النحو والصرف — قواعد اللغة العربية كاملة',
                'slug'           => 'arabic-grammar-complete',
                'description'    => 'كورس متكامل في قواعد اللغة العربية النحو والصرف للمرحلة الثانوية',
                'price'          => 12900,
                'discount_price' => 8900,
                'grade_level'    => 'all',
                'level'          => 'beginner',
                'teacher_idx'    => 2,
                'subject_idx'    => 4,
            ],
            [
                'title'          => 'الأحياء — التشريح ووظائف الأعضاء',
                'slug'           => 'biology-anatomy-functions',
                'description'    => 'شرح تفصيلي لجسم الإنسان ووظائف الأجهزة الحيوية مع رسوم توضيحية',
                'price'          => 16900,
                'discount_price' => null,
                'grade_level'    => 'grade_11',
                'level'          => 'intermediate',
                'teacher_idx'    => 3,
                'subject_idx'    => 3,
            ],
            [
                'title'          => 'الرياضيات — الإحصاء والاحتمالات',
                'slug'           => 'math-statistics-probability',
                'description'    => 'وحدة الإحصاء والاحتمالات بأسلوب مبسط مع تطبيقات واقعية',
                'price'          => 11900,
                'discount_price' => 7900,
                'grade_level'    => 'grade_10',
                'level'          => 'beginner',
                'teacher_idx'    => 0,
                'subject_idx'    => 0,
            ],
            [
                'title'          => 'اللغة الإنجليزية — Grammar & Writing Advanced',
                'slug'           => 'english-grammar-writing-advanced',
                'description'    => 'Advanced English grammar and academic writing skills for Tawjihi students',
                'price'          => 18900,
                'discount_price' => 13900,
                'grade_level'    => 'grade_12',
                'level'          => 'advanced',
                'teacher_idx'    => 2,
                'subject_idx'    => 5,
            ],
            [
                'title'          => 'التاريخ — الحضارات القديمة',
                'slug'           => 'history-ancient-civilizations',
                'description'    => 'رحلة تاريخية شاملة في أبرز الحضارات القديمة وتأثيرها على العالم الحديث',
                'price'          => 9900,
                'discount_price' => null,
                'grade_level'    => 'grade_10',
                'level'          => 'beginner',
                'teacher_idx'    => 4,
                'subject_idx'    => 6,
            ],
            [
                'title'          => 'الجغرافيا — الجغرافيا الطبيعية والبشرية',
                'slug'           => 'geography-physical-human',
                'description'    => 'دراسة وافية لمادة الجغرافيا الطبيعية والبشرية مع خرائط تفاعلية',
                'price'          => 10900,
                'discount_price' => 7900,
                'grade_level'    => 'grade_10',
                'level'          => 'beginner',
                'teacher_idx'    => 4,
                'subject_idx'    => 7,
            ],
            [
                'title'          => 'مراجعة شاملة — مواد العلوم للتوجيهي',
                'slug'           => 'tawjihi-science-comprehensive-review',
                'description'    => 'مراجعة نهائية شاملة لكل مواد العلوم (فيزياء، كيمياء، أحياء) لطلاب الصف الثاني عشر',
                'price'          => 24900,
                'discount_price' => 18900,
                'grade_level'    => 'grade_12',
                'level'          => 'advanced',
                'teacher_idx'    => 3,
                'subject_idx'    => 1,
            ],
        ];

        $courseModels = [];
        foreach ($coursesData as $courseData) {
            $teacher  = $teacherUsers[$courseData['teacher_idx']];
            $subject  = $subjectModels[$courseData['subject_idx']];

            $course = Course::firstOrCreate(
                ['slug' => $courseData['slug']],
                [
                    'teacher_id'     => $teacher->id,
                    'subject_id'     => $subject->id,
                    'title'          => $courseData['title'],
                    'description'    => $courseData['description'],
                    'price'          => $courseData['price'],
                    'discount_price' => $courseData['discount_price'],
                    'grade_level'    => $courseData['grade_level'],
                    'level'          => $courseData['level'],
                    'is_published'   => true,
                ]
            );
            $courseModels[] = $course;
        }

        // ─── 7. Lessons (3-5 per course) ─────────────────────────────
        foreach ($courseModels as $course) {
            $lessonTitles = [
                "مقدمة الكورس والخطة الدراسية",
                "الوحدة الأولى — المفاهيم الأساسية",
                "الوحدة الثانية — التطبيقات العملية",
                "الوحدة الثالثة — مسائل ونماذج محلولة",
                "المراجعة النهائية والاختبار",
            ];

            foreach ($lessonTitles as $index => $title) {
                CourseLesson::firstOrCreate(
                    ['course_id' => $course->id, 'order' => $index + 1],
                    [
                        'title'            => $title,
                        'video_url'        => "https://example.com/videos/course-{$course->id}-lesson-" . ($index + 1) . ".mp4",
                        'duration_seconds' => rand(900, 3600),
                        'is_free_preview'  => $index === 0, // First lesson is free preview
                    ]
                );
            }
        }

        // ─── 8. Sample Enrollments ────────────────────────────────────
        // Enroll first 10 students in 2-3 courses each
        foreach (array_slice($studentUsers, 0, 10) as $index => $student) {
            $coursesToEnroll = array_slice($courseModels, $index % count($courseModels), 2);
            foreach ($coursesToEnroll as $course) {
                Enrollment::firstOrCreate(
                    ['user_id' => $student->id, 'course_id' => $course->id],
                    [
                        'progress_percent' => rand(0, 100),
                        'enrolled_at'      => now()->subDays(rand(1, 60)),
                    ]
                );
            }
        }

        // ─── 9. Platform Settings ─────────────────────────────────────
        \DB::table('platform_settings')->insertOrIgnore([
            ['key' => 'commission_percent', 'value' => '20', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'platform_name',      'value' => 'التفوق',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'platform_email',     'value' => 'support@altafawwuq.com', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->command->info('✅ Database seeded successfully!');
        $this->command->info('   Admin:   admin@altafawwuq.com / password');
        $this->command->info('   Teacher: teacher1@altafawwuq.com / password');
        $this->command->info('   Student: student1@altafawwuq.com / password');
    }
}
