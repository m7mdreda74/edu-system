<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\CourseLesson;
use App\Domain\Course\Models\Subject;
use App\Domain\Course\Models\GradeLevel;
use App\Domain\Course\Models\Worksheet;
use App\Domain\Course\Models\WorksheetSubmission;
use App\Domain\Course\Models\Review;
use App\Domain\Course\Models\LiveSession;
use App\Domain\Course\Models\LessonQuestion;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\LessonProgress;
use App\Domain\Enrollment\Models\PurchaseRequest;
use App\Domain\User\Models\User;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizQuestion;
use App\Domain\Quiz\Models\QuizOption;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Payment\Models\Coupon;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\Invoice;
use App\Domain\Payment\Models\TeacherPayout;
use App\Domain\Communication\Models\Conversation;
use App\Domain\Communication\Models\ChatMessage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 1. Clean Database Tables ────────────────────────────────
        $this->command->info('🧹 Cleaning database tables...');
        Schema::disableForeignKeyConstraints();
        
        DB::table('users')->truncate();
        DB::table('courses')->truncate();
        DB::table('course_lessons')->truncate();
        DB::table('subjects')->truncate();
        DB::table('enrollments')->truncate();
        DB::table('quizzes')->truncate();
        DB::table('quiz_questions')->truncate();
        DB::table('quiz_options')->truncate();
        DB::table('quiz_attempts')->truncate();
        DB::table('grade_levels')->truncate();
        DB::table('coupons')->truncate();
        DB::table('payments')->truncate();
        DB::table('invoices')->truncate();
        DB::table('teacher_payouts')->truncate();
        DB::table('worksheets')->truncate();
        DB::table('worksheet_submissions')->truncate();
        DB::table('lesson_progress')->truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('parent_student_links')->truncate();
        DB::table('conversations')->truncate();
        DB::table('chat_messages')->truncate();
        DB::table('live_sessions')->truncate();
        DB::table('live_session_attendees')->truncate();
        DB::table('reviews')->truncate();

        Schema::enableForeignKeyConstraints();

        // ─── 2. Create Roles ─────────────────────────────────────────
        $this->command->info('🔑 Initializing roles...');
        $adminRole   = Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $studentRole = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $parentRole  = Role::firstOrCreate(['name' => 'parent',  'guard_name' => 'web']);

        // ─── 3. Seed Grade Levels ────────────────────────────────────
        $this->command->info('🏫 Seeding grade levels...');
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
        $this->command->info('📚 Seeding subjects...');
        $subjectsData = [
            ['name' => 'الرياضيات', 'name_en' => 'Mathematics', 'grade_level' => 'all', 'icon' => 'calculator'],
            ['name' => 'العلوم العامة', 'name_en' => 'General Science', 'grade_level' => 'grade_5', 'icon' => 'flask'],
            ['name' => 'الفيزياء', 'name_en' => 'Physics', 'grade_level' => 'grade_11', 'icon' => 'atom'],
            ['name' => 'الكيمياء', 'name_en' => 'Chemistry', 'grade_level' => 'grade_11', 'icon' => 'flask'],
            ['name' => 'الأحياء', 'name_en' => 'Biology', 'grade_level' => 'grade_11', 'icon' => 'dna'],
            ['name' => 'اللغة العربية', 'name_en' => 'Arabic', 'grade_level' => 'all', 'icon' => 'book'],
            ['name' => 'اللغة الإنجليزية', 'name_en' => 'English', 'grade_level' => 'all', 'icon' => 'language'],
            ['name' => 'الدراسات الاجتماعية', 'name_en' => 'Social Studies', 'grade_level' => 'grade_8', 'icon' => 'landmark'],
            ['name' => 'التاريخ', 'name_en' => 'History', 'grade_level' => 'grade_9', 'icon' => 'history'],
            ['name' => 'الجغرافيا', 'name_en' => 'Geography', 'grade_level' => 'grade_8', 'icon' => 'globe'],
            ['name' => 'الحاسب الآلي', 'name_en' => 'Computer Science', 'grade_level' => 'all', 'icon' => 'laptop'],
            ['name' => 'التربية الإسلامية', 'name_en' => 'Islamic Studies', 'grade_level' => 'all', 'icon' => 'book-open'],
            ['name' => 'اللغة الفرنسية', 'name_en' => 'French', 'grade_level' => 'all', 'icon' => 'graduation-cap'],
        ];

        $subjectModels = [];
        foreach ($subjectsData as $s) {
            $subjectModels[$s['name']] = Subject::create($s);
        }

        // ─── 5. Admin User ───────────────────────────────────────────
        $this->command->info('👤 Creating admin account...');
        $admin = User::create([
            'name'              => 'مدير المنصة',
            'email'             => 'admin@altafawwuq.com',
            'password'          => Hash::make('password'),
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole($adminRole);

        // ─── 6. Teachers ─────────────────────────────────────────────
        $this->command->info('👨‍🏫 Seeding 10 teachers with avatars...');
        $teachersData = [
            [
                'name' => 'أ. أحمد المنصوري',
                'email' => 'teacher.ahmed@altafawwuq.com',
                'bio' => 'معلم أول رياضيات، خبرة 15 سنة في تدريس الثانوية العامة وتأسيس الطلاب المتميزين.',
                'subjects' => ['الرياضيات'],
            ],
            [
                'name' => 'د. سارة عبد الرحمن',
                'email' => 'teacher.sara@altafawwuq.com',
                'bio' => 'دكتوراة في الفيزياء التطبيقية، متميزة في تبسيط الفيزياء للمرحلتين الإعدادية والثانوية العامة.',
                'subjects' => ['الفيزياء', 'العلوم العامة'],
            ],
            [
                'name' => 'أ. خالد الهاشمي',
                'email' => 'teacher.khaled@altafawwuq.com',
                'bio' => 'معلم لغة عربية وشغوف بالبلاغة والنحو وتأسيس الأطفال بأسلوب أدبي تفاعلي.',
                'subjects' => ['اللغة العربية'],
            ],
            [
                'name' => 'أ. مها العتيبي',
                'email' => 'teacher.maha@altafawwuq.com',
                'bio' => 'معلمة لغة إنجليزية حاصلة على شهادة CELTA، متخصصة في المحادثة وتأسيس اللغات بمختلف المراحل.',
                'subjects' => ['اللغة الإنجليزية'],
            ],
            [
                'name' => 'أ. طارق السويدان',
                'email' => 'teacher.tareq@altafawwuq.com',
                'bio' => 'معلم مادة الدراسات الاجتماعية والتاريخ، صاحب أسلوب قصصي ممتع ومؤلف لعدة ملخصات دراسية.',
                'subjects' => ['الدراسات الاجتماعية', 'التاريخ'],
            ],
            [
                'name' => 'د. ليلى الفارس',
                'email' => 'teacher.layla@altafawwuq.com',
                'bio' => 'معلمة مادة الأحياء والعلوم، دكتوراة في علم الأحياء الدقيقة وخبرة 8 سنوات في التدريس التفاعلي.',
                'subjects' => ['الأحياء', 'العلوم العامة'],
            ],
            [
                'name' => 'أ. يوسف الحداد',
                'email' => 'teacher.youssef@altafawwuq.com',
                'bio' => 'معلم كيمياء، مبتكر لطرق تدريبية ممتعة لشرح الكيمياء العضوية ومعادلاتها لطلاب الثانوية.',
                'subjects' => ['الكيمياء'],
            ],
            [
                'name' => 'أ. فاطمة الكواري',
                'email' => 'teacher.fatma@altafawwuq.com',
                'bio' => 'معلمة حاسب آلي وتكنولوجيا معلومات، متخصصة في لغات البرمجة للأطفال وتطوير مهارات التفكير البرمجي.',
                'subjects' => ['الحاسب الآلي'],
            ],
            [
                'name' => 'أ. عبد الله الشمري',
                'email' => 'teacher.abdullah@altafawwuq.com',
                'bio' => 'معلم تربية إسلامية وقرآن كريم، متميز بأسلوبه السلس المستنبط من هدي السنة النبوية العطرة.',
                'subjects' => ['التربية الإسلامية'],
            ],
            [
                'name' => 'أ. رانيا الشافعي',
                'email' => 'teacher.rania@altafawwuq.com',
                'bio' => 'معلمة لغة فرنسية، خبرة طويلة في المدارس الدولية واللغات وتدريب الطلاب لاختبارات DELF.',
                'subjects' => ['اللغة الفرنسية'],
            ],
        ];

        $teacherModels = [];
        foreach ($teachersData as $index => $t) {
            $avatarPath = $this->generateSeededImage("Teacher " . ($index + 1), 'avatars', 180, 180, '#7A1C37');
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
            
            // Map the teacher's name to the user model for easy mapping later
            $teacherModels[$t['name']] = $teacher;
        }

        // ─── 7. Students ─────────────────────────────────────────────
        $this->command->info('🧑‍🎓 Seeding 60 students distributed across grades...');
        
        $firstNames = ['محمد', 'أحمد', 'عبد الله', 'خالد', 'علي', 'يوسف', 'عمر', 'فهد', 'سعد', 'عبد الرحمن', 'عبد العزيز', 'سلطان', 'فيصل', 'سليمان', 'حمد', 'فاطمة', 'مريم', 'نورة', 'سارة', 'دلال', 'ريم', 'هند', 'أمل', 'منى', 'عائشة', 'شيخة', 'الجوهرة', 'موزة', 'وضحى', 'غادة'];
        $lastNames = ['العتيبي', 'الهاشمي', 'المنصوري', 'الشمري', 'التميمي', 'الدوسري', 'القحطاني', 'المالكي', 'الحربي', 'المطيري', 'الغامدي', 'الزهراني', 'العنزي', 'الخالدي', 'الرشيدي', 'السبيعي', 'البقمي', 'السديري', 'الشريف', 'الكواري'];
        
        // Generate a pool of 6 student avatar images to reuse and save time/storage
        $studentAvatarsPool = [];
        for ($i = 1; $i <= 6; $i++) {
            $studentAvatarsPool[] = $this->generateSeededImage("Student " . $i, 'avatars', 150, 150, '#1C377A');
        }

        $studentModels = [];
        $gradesKeys = ['grade_1', 'grade_2', 'grade_3', 'grade_4', 'grade_5', 'grade_6', 'grade_7', 'grade_8', 'grade_9', 'grade_10', 'grade_11', 'grade_12'];

        for ($i = 1; $i <= 60; $i++) {
            $fullName = $firstNames[array_rand($firstNames)] . ' ' . $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
            $grade = $gradesKeys[($i - 1) % count($gradesKeys)]; // Evenly distribute students across grade levels
            
            $student = User::create([
                'name'              => $fullName,
                'email'             => "student{$i}@altafawwuq.com",
                'grade_level'       => $grade,
                'avatar'            => $studentAvatarsPool[array_rand($studentAvatarsPool)],
                'password'          => Hash::make('password'),
                'is_active'         => true,
                'email_verified_at' => now(),
            ]);
            $student->assignRole($studentRole);
            $studentModels[] = $student;
        }

        // ─── 8. Parents ──────────────────────────────────────────────
        $this->command->info('👪 Seeding 15 parents and linking them...');
        $parentModels = [];
        for ($i = 1; $i <= 15; $i++) {
            $lastName = $lastNames[($i - 1) % count($lastNames)];
            $parentName = (rand(0, 1) ? 'أبو ' : 'أم ') . $firstNames[array_rand($firstNames)] . ' ' . $lastName;
            $parent = User::create([
                'name'              => $parentName,
                'email'             => "parent{$i}@altafawwuq.com",
                'password'          => Hash::make('password'),
                'is_active'         => true,
                'email_verified_at' => now(),
            ]);
            $parent->assignRole($parentRole);
            $parentModels[] = $parent;

            // Link this parent to 1-2 random students
            $numberOfChildren = rand(1, 2);
            $linkedStudents = (array) array_rand($studentModels, $numberOfChildren);
            foreach ($linkedStudents as $studentIdx) {
                ParentStudentLink::create([
                    'parent_user_id'  => $parent->id,
                    'student_user_id' => $studentModels[$studentIdx]->id,
                    'relationship'    => rand(0, 1) ? 'father' : 'mother',
                    'verified_at'     => now(),
                ]);
            }
        }

        // ─── 9. Courses (33 Courses) ─────────────────────────────────
        $this->command->info('📖 Seeding 33 detailed courses...');
        $coursesData = [
            // الرياضيات
            [
                'title' => 'تأسيس الحساب الذهني للأطفال والضرب السريع',
                'slug' => 'mental-math-kids',
                'description' => 'كورس تدريبي ممتع ومبسط للأطفال لتنمية مهارات الحساب الذهني والجمع والضرب السريع بدون آلة حاسبة.',
                'price' => 5000,
                'discount_price' => 3500,
                'grade_level' => 'grade_2',
                'level' => 'beginner',
                'subject' => 'الرياضيات',
                'teacher' => 'أ. أحمد المنصوري'
            ],
            [
                'title' => 'الرياضيات المالية والنسب المئوية وحل المشكلات',
                'slug' => 'financial-math-preparatory',
                'description' => 'شرح عملي وتطبيقي للنسب المئوية، الفوائد، التناسب الطردي والعكسي وتطبيقاتها الحياتية لطلاب الإعدادية.',
                'price' => 8000,
                'discount_price' => null,
                'grade_level' => 'grade_8',
                'level' => 'intermediate',
                'subject' => 'الرياضيات',
                'teacher' => 'أ. أحمد المنصوري'
            ],
            [
                'title' => 'الجبر والهندسة الفراغية للصف الحادي عشر',
                'slug' => 'algebra-geometry-grade11',
                'description' => 'كورس متكامل يغطي المصفوفات، المحددات، المتجهات، والمعادلات الرياضية الفراغية في ثلاثة أبعاد.',
                'price' => 12000,
                'discount_price' => 9900,
                'grade_level' => 'grade_11',
                'level' => 'intermediate',
                'subject' => 'الرياضيات',
                'teacher' => 'أ. أحمد المنصوري'
            ],
            [
                'title' => 'التفاضل والتكامل من التأسيس إلى التفوق للثانوية العامة',
                'slug' => 'calculus-advanced-grade12',
                'description' => 'المرجع الشامل في النهايات، المشتقات، التكامل المحدد وغير المحدد، وتطبيقاته الهندسية والفيزيائية مع حل النماذج الوزارية.',
                'price' => 20000,
                'discount_price' => 14900,
                'grade_level' => 'grade_12',
                'level' => 'advanced',
                'subject' => 'الرياضيات',
                'teacher' => 'أ. أحمد المنصوري'
            ],

            // العلوم العامة
            [
                'title' => 'أسرار جسم الإنسان ورحلة داخل الخلايا والأجهزة الحيوية',
                'slug' => 'human-body-secrets-grade5',
                'description' => 'شرح تفاعلي بالصور والفيديوهات للجهاز الدوري والتنفسي والهضمي في جسم الإنسان بأسلوب شيق وبسيط يناسب الصف الخامس.',
                'price' => 6000,
                'discount_price' => 4500,
                'grade_level' => 'grade_5',
                'level' => 'beginner',
                'subject' => 'العلوم العامة',
                'teacher' => 'د. ليلى الفارس'
            ],
            [
                'title' => 'النظام البيئي وتنوع الكائنات الحية والتوازن الطبيعي',
                'slug' => 'ecosystem-nature-grade6',
                'description' => 'سنتعلم في هذا الكورس عن الشبكات الغذائية، أثر الإنسان على البيئة، وكيفية الحفاظ على التوازن البيئي والتنوع البيولوجي.',
                'price' => 0, // مجاني
                'discount_price' => null,
                'grade_level' => 'grade_6',
                'level' => 'beginner',
                'subject' => 'العلوم العامة',
                'teacher' => 'د. سارة عبد الرحمن'
            ],

            // الفيزياء
            [
                'title' => 'الميكانيكا الكلاسيكية وقوانين الحركة للصف العاشر',
                'slug' => 'classical-mechanics-grade10',
                'description' => 'شرح شامل لقوانين نيوتن للحركة، السرعة، التسارع، والمقذوفات مع تطبيقات وتمارين عملية.',
                'price' => 9000,
                'discount_price' => 7000,
                'grade_level' => 'grade_10',
                'level' => 'beginner',
                'subject' => 'الفيزياء',
                'teacher' => 'د. سارة عبد الرحمن'
            ],
            [
                'title' => 'الكهربية الساكنة والتيار المستمر والمغناطيسية',
                'slug' => 'electricity-magnetism-grade11',
                'description' => 'شرح مفصل للمقاومة وقانون أوم وتوصيل المقاومات على التوالي والتوازي، مع دراسة الحث الكهرومغناطيسي.',
                'price' => 15000,
                'discount_price' => 11500,
                'grade_level' => 'grade_11',
                'level' => 'intermediate',
                'subject' => 'الفيزياء',
                'teacher' => 'د. سارة عبد الرحمن'
            ],
            [
                'title' => 'الفيزياء الحديثة والنووية لشهادة الثانوية العامة',
                'slug' => 'modern-physics-grade12',
                'description' => 'تبسيط الفيزياء الذرية والنووية، طيف الهيدروجين، الليزر، ونظرية أينشتاين للنسبية الخاصة وحل أسئلة الامتحانات السابقة.',
                'price' => 18000,
                'discount_price' => null,
                'grade_level' => 'grade_12',
                'level' => 'advanced',
                'subject' => 'الفيزياء',
                'teacher' => 'د. سارة عبد الرحمن'
            ],

            // الكيمياء
            [
                'title' => 'مبادئ الكيمياء وجدول العناصر والروابط الكيميائية',
                'slug' => 'chemistry-foundations-grade9',
                'description' => 'تأسيس متكامل لطلاب الصف التاسع يشرح الذرة، وتوزيع الإلكترونات، وأنواع الروابط التساهمية والأيونية وكتابة الصيغ الكيميائية.',
                'price' => 7500,
                'discount_price' => 5000,
                'grade_level' => 'grade_9',
                'level' => 'beginner',
                'subject' => 'الكيمياء',
                'teacher' => 'أ. يوسف الحداد'
            ],
            [
                'title' => 'الكيمياء العضوية وتفاعلات الهيدروكربونات للصف الحادي عشر',
                'slug' => 'organic-chemistry-grade11',
                'description' => 'شرح مفصل وممتع لعالم الكربون: الألكانات، الألكينات، الألكاينات، والمجموعات الوظيفية وتسميتها الكيميائية وتفاعلاتها.',
                'price' => 14000,
                'discount_price' => 11000,
                'grade_level' => 'grade_11',
                'level' => 'intermediate',
                'subject' => 'الكيمياء',
                'teacher' => 'أ. يوسف الحداد'
            ],
            [
                'title' => 'الكيمياء التحليلية والكهربائية وحسابات الاتزان الكيميائي',
                'slug' => 'electrochemistry-analytical-grade12',
                'description' => 'حل معادلات الأكسدة والاختزال، الخلايا الجلفانية، وقوانين فاراداي، وحساب ثابت الاتزان لطلاب الثانوية العامة.',
                'price' => 16000,
                'discount_price' => 12900,
                'grade_level' => 'grade_12',
                'level' => 'advanced',
                'subject' => 'الكيمياء',
                'teacher' => 'أ. يوسف الحداد'
            ],

            // الأحياء
            [
                'title' => 'علم الوراثة والـ DNA وقوانين مندل للمرحلة الثانوية',
                'slug' => 'genetics-dna-grade11',
                'description' => 'كورس تفصيلي يغطي قوانين مندل للوراثة، تركيب الكروموسومات، كيفية نسخ الـ DNA، والترجمة الجينية بشكل مبسط بالرسومات.',
                'price' => 13000,
                'discount_price' => 9500,
                'grade_level' => 'grade_11',
                'level' => 'intermediate',
                'subject' => 'الأحياء',
                'teacher' => 'د. ليلى الفارس'
            ],
            [
                'title' => 'علم وظائف الأعضاء وجهاز المناعة البشري بالتفصيل',
                'slug' => 'physiology-immunity-grade12',
                'description' => 'شرح متقدم لجهاز الغدد الصماء، الجهاز العصبي، وبنية جهاز المناعة وخطوط الدفاع لحماية الجسم من الميكروبات.',
                'price' => 17000,
                'discount_price' => null,
                'grade_level' => 'grade_12',
                'level' => 'advanced',
                'subject' => 'الأحياء',
                'teacher' => 'د. ليلى الفارس'
            ],

            // اللغة العربية
            [
                'title' => 'تأسيس اللغة العربية وقواعد القراءة والإملاء والتعبير الصحيح',
                'slug' => 'arabic-foundations-kids',
                'description' => 'دورة تأسيسية للأطفال وطلاب المرحلة الابتدائية الأولى لتعلم القراءة الصحيحة، نطق الحروف، وقواعد الإملاء مثل التنوين والمد والشدة.',
                'price' => 4000,
                'discount_price' => 2500,
                'grade_level' => 'grade_3',
                'level' => 'beginner',
                'subject' => 'اللغة العربية',
                'teacher' => 'أ. خالد الهاشمي'
            ],
            [
                'title' => 'النحو الميسر لطلاب المرحلة الإعدادية - من المبتدأ إلى المجرور',
                'slug' => 'simplified-arabic-grammar',
                'description' => 'دورة متكاملة تبسط قواعد الإعراب، الجملة الاسمية والفعلية، كان وأخواتها، إن وأخواتها، والمنصوبات والمجرورات بالأمثلة التفاعلية.',
                'price' => 7000,
                'discount_price' => null,
                'grade_level' => 'grade_8',
                'level' => 'intermediate',
                'subject' => 'اللغة العربية',
                'teacher' => 'أ. خالد الهاشمي'
            ],
            [
                'title' => 'بلاغة النصوص العربية والمحسنات البديعية وتذوق الأدب',
                'slug' => 'arabic-rhetoric-literature',
                'description' => 'شرح مبسط لعلم البيان (التشبيه والاستعارة والكناية)، وعلم البديع، وتذوق نصوص الشعر والنثر للمرحلة الثانوية.',
                'price' => 10000,
                'discount_price' => 8000,
                'grade_level' => 'grade_11',
                'level' => 'intermediate',
                'subject' => 'اللغة العربية',
                'teacher' => 'أ. خالد الهاشمي'
            ],
            [
                'title' => 'مراجعة النحو والصرف النهائية لطلاب الثانوية العامة',
                'slug' => 'arabic-final-revision-grade12',
                'description' => 'المراجعة الشاملة لجميع وحدات النحو والصرف، إعراب الأفعال والأسماء، والممنوع من الصرف مع حل اختبارات وزارية متعددة.',
                'price' => 15000,
                'discount_price' => 11000,
                'grade_level' => 'grade_12',
                'level' => 'advanced',
                'subject' => 'اللغة العربية',
                'teacher' => 'أ. خالد الهاشمي'
            ],

            // اللغة الإنجليزية
            [
                'title' => 'English Phonics & Speaking Basics for Young Learners',
                'slug' => 'english-phonics-kids',
                'description' => 'Learn English letters, phonics sounds, simple vocabulary and basic conversation phrases in a highly visual way.',
                'price' => 4500,
                'discount_price' => 3000,
                'grade_level' => 'grade_1',
                'level' => 'beginner',
                'subject' => 'اللغة الإنجليزية',
                'teacher' => 'أ. مها العتيبي'
            ],
            [
                'title' => 'Essential English Grammar & Vocabulary for School Students',
                'slug' => 'english-grammar-preparatory',
                'description' => 'Master English tenses (Present, Past, Future), pronouns, sentence building, and everyday conversation skills for school.',
                'price' => 8000,
                'discount_price' => null,
                'grade_level' => 'grade_7',
                'level' => 'intermediate',
                'subject' => 'اللغة الإنجليزية',
                'teacher' => 'أ. مها العتيبي'
            ],
            [
                'title' => 'Advanced English Writing Skills & Essay Composition',
                'slug' => 'advanced-english-writing',
                'description' => 'Learn how to write professional paragraphs, argumentative and descriptive essays, using transition words and advanced structures.',
                'price' => 11000,
                'discount_price' => 9000,
                'grade_level' => 'grade_11',
                'level' => 'intermediate',
                'subject' => 'اللغة الإنجليزية',
                'teacher' => 'أ. مها العتيبي'
            ],
            [
                'title' => 'TOEFL & IELTS Preparation Course — Master All Four Sections',
                'slug' => 'toefl-ielts-preparation',
                'description' => 'Complete guide for high school and university students to pass English proficiency tests. Tips and practices for Reading, Writing, Listening, and Speaking.',
                'price' => 25000,
                'discount_price' => 19900,
                'grade_level' => 'grade_12',
                'level' => 'advanced',
                'subject' => 'اللغة الإنجليزية',
                'teacher' => 'أ. مها العتيبي'
            ],

            // الدراسات الاجتماعية، التاريخ، الجغرافيا
            [
                'title' => 'تراثنا وتاريخنا العربي الإسلامي القديم والحديث',
                'slug' => 'arabic-islamic-history',
                'description' => 'رحلة تاريخية لطلاب الصف الثامن تستعرض نشأة الحضارة الإسلامية، الفتوحات، وأهم الشخصيات التاريخية المؤثرة.',
                'price' => 0,
                'discount_price' => null,
                'grade_level' => 'grade_8',
                'level' => 'beginner',
                'subject' => 'الدراسات الاجتماعية',
                'teacher' => 'أ. طارق السويدان'
            ],
            [
                'title' => 'جغرافية الوطن العربي وثرواته الطبيعية وموارده الاقتصادية',
                'slug' => 'arabic-world-geography',
                'description' => 'دراسة شاملة لخصائص تضاريس ومناخ وسكان الوطن العربي، وأهم المضايق والموانئ والنشاطات الاقتصادية لطلاب الصف السابع.',
                'price' => 5000,
                'discount_price' => 3500,
                'grade_level' => 'grade_7',
                'level' => 'beginner',
                'subject' => 'الجغرافيا',
                'teacher' => 'أ. طارق السويدان'
            ],
            [
                'title' => 'تاريخ الحضارات الإنسانية العريقة - الفراعنة وبابل والرومان',
                'slug' => 'ancient-civilizations-history',
                'description' => 'استكشاف شامل وممتع لأسرار الحضارات القديمة، معالمها المعمارية ونظامها الاجتماعي للصف التاسع.',
                'price' => 6500,
                'discount_price' => null,
                'grade_level' => 'grade_9',
                'level' => 'beginner',
                'subject' => 'التاريخ',
                'teacher' => 'أ. طارق السويدان'
            ],
            [
                'title' => 'تاريخ العالم الحديث والمعاصر والثورات الكبرى',
                'slug' => 'modern-world-history',
                'description' => 'شرح تاريخي مشوق للحرب العالمية الأولى والثانية، الثورة الصناعية، وتأسيس المنظمات الدولية لطلاب الثانوية العامة.',
                'price' => 11000,
                'discount_price' => 8500,
                'grade_level' => 'grade_11',
                'level' => 'intermediate',
                'subject' => 'التاريخ',
                'teacher' => 'أ. طارق السويدان'
            ],

            // الحاسب الآلي
            [
                'title' => 'مدخل إلى البرمجة وحل المشكلات باستخدام لغة Scratch المرئية',
                'slug' => 'scratch-programming-kids',
                'description' => 'كورس عملي يعلم الأطفال والطلاب المبتدئين التفكير المنطقي وكتابة الألعاب والقصص التفاعلية بالبرمجة المرئية دون كتابة كود معقد.',
                'price' => 6000,
                'discount_price' => 4000,
                'grade_level' => 'grade_5',
                'level' => 'beginner',
                'subject' => 'الحاسب الآلي',
                'teacher' => 'أ. فاطمة الكواري'
            ],
            [
                'title' => 'تصميم وتطوير صفحات الويب باستخدام HTML5 & CSS3',
                'slug' => 'web-design-html-css',
                'description' => 'كورس تطبيقي يعلمك كيف تبني موقعك الإلكتروني الأول من الصفر باستخدام أكواد الهيكل والتنسيق خطوة بخطوة.',
                'price' => 9500,
                'discount_price' => null,
                'grade_level' => 'grade_8',
                'level' => 'intermediate',
                'subject' => 'الحاسب الآلي',
                'teacher' => 'أ. فاطمة الكواري'
            ],
            [
                'title' => 'أساسيات البرمجة بلغة Python للمبتدئين ولطلاب الثانوية',
                'slug' => 'python-programming-basics',
                'description' => 'تعلم المتغيرات، الدوال، الحلقات التكرارية، وهياكل البيانات في بايثون وبناء تطبيقات وألعاب صغيرة تفاعلية.',
                'price' => 14000,
                'discount_price' => 10000,
                'grade_level' => 'grade_10',
                'level' => 'intermediate',
                'subject' => 'الحاسب الآلي',
                'teacher' => 'أ. فاطمة الكواري'
            ],

            // التربية الإسلامية
            [
                'title' => 'تفسير قصار السور من جزء عم وأخلاق المسلم الصغير',
                'slug' => 'islamic-basics-kids',
                'description' => 'شرح مبسط لمعاني سور القرآن الكريم القصيرة، وقصص من السيرة النبوية تعلم الأطفال الصدق والأمانة وبر الوالدين.',
                'price' => 0,
                'discount_price' => null,
                'grade_level' => 'grade_4',
                'level' => 'beginner',
                'subject' => 'التربية الإسلامية',
                'teacher' => 'أ. عبد الله الشمري'
            ],
            [
                'title' => 'فقه العبادات والمعاملات اليومية الميسر للمرحلة الإعدادية',
                'slug' => 'simplified-fiqh-preparatory',
                'description' => 'دراسة شاملة لأحكام الطهارة، الصلاة، الصوم، والزكاة بأسلوب فقهي ميسر ومطابق للمناهج المدرسية.',
                'price' => 5000,
                'discount_price' => null,
                'grade_level' => 'grade_8',
                'level' => 'intermediate',
                'subject' => 'التربية الإسلامية',
                'teacher' => 'أ. عبد الله الشمري'
            ],

            // اللغة الفرنسية
            [
                'title' => 'French Basics for Beginners (Le Français Facile)',
                'slug' => 'french-basics-preparatory',
                'description' => 'Learn French greetings, numbers, alphabets, basic verbs (être, avoir) and simple structures for school students.',
                'price' => 7000,
                'discount_price' => 4900,
                'grade_level' => 'grade_7',
                'level' => 'beginner',
                'subject' => 'اللغة الفرنسية',
                'teacher' => 'أ. رانيا الشافعي'
            ],
            [
                'title' => 'Intermediate French Grammar & DELF A2 Preparation',
                'slug' => 'french-intermediate-grammar',
                'description' => 'Enhance your French conjugation (passé composé, futur simple), vocabulary, and conversational skills to pass standard exams.',
                'price' => 12000,
                'discount_price' => 9500,
                'grade_level' => 'grade_10',
                'level' => 'intermediate',
                'subject' => 'اللغة الفرنسية',
                'teacher' => 'أ. رانيا الشافعي'
            ],
        ];

        $courseModels = [];
        foreach ($coursesData as $c) {
            $thumbnailPath = $this->generateSeededImage($c['slug'], 'thumbnails', 640, 360, '#7A1C37');
            $teacherModel = $teacherModels[$c['teacher']];
            $subjectModel = $subjectModels[$c['subject']];

            $courseModels[] = Course::create([
                'teacher_id'     => $teacherModel->id,
                'subject_id'     => $subjectModel->id,
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

        // ─── 10. Lessons (5 to 8 per course) ──────────────────────────
        $this->command->info('📝 Seeding lessons for all courses...');
        $lessonNamesTemplate = [
            'الدرس الأول: مقدمة وتوجيهات عامة للمنهج الدراسي',
            'الدرس الثاني: شرح المفاهيم الأساسية والقواعد العامة',
            'الدرس الثالث: تحليل معمق وأمثلة تطبيقية وشروحات مرئية',
            'الدرس الرابع: تطبيقات تفاعلية وأنشطة عملية لتأصيل الفهم',
            'الدرس الخامس: ورشة عمل تفصيلية وحل المشكلات والمسائل الصعبة',
            'الدرس السادس: مراجعة شاملة وحل بنك الأسئلة المتوقعة',
            'الدرس السابع: مراجعة وحل نماذج امتحانات السنوات السابقة',
            'الدرس الثامن: نصائح ذهبية وإرشادات أخيرة للاختبار النهائي',
        ];

        foreach ($courseModels as $course) {
            $lessonCount = rand(5, 8);
            $totalDuration = 0;
            for ($idx = 0; $idx < $lessonCount; $idx++) {
                $duration = rand(900, 3600); // 15 to 60 mins
                $totalDuration += $duration;

                CourseLesson::create([
                    'course_id'        => $course->id,
                    'title'            => $lessonNamesTemplate[$idx],
                    'video_url'        => 'https://example.com/videos/seeded-video-' . $course->id . '-' . ($idx + 1) . '.mp4',
                    'duration_seconds' => $duration,
                    'order'            => $idx + 1,
                    'is_free_preview'  => $idx === 0, // First lesson is free preview
                ]);
            }
            // Update cached statistics on course
            $course->update([
                'total_lessons' => $lessonCount,
                'total_duration' => $totalDuration,
            ]);
        }

        // ─── 11. Quizzes & Options (1 per course, 3-4 questions) ───────
        $this->command->info('❓ Seeding quizzes and question options...');
        foreach ($courseModels as $course) {
            $quiz = Quiz::create([
                'course_id'          => $course->id,
                'title'              => "الاختبار التقيمي الأول لـ: {$course->title}",
                'time_limit_minutes' => rand(15, 45),
                'passing_score'      => 60,
                'is_active'          => true,
            ]);

            $questions = [
                [
                    'question_text' => 'السؤال الأول: ما هو المفهوم الأساسي الوارد في الفصل الأول من الكورس؟',
                    'options' => ['الإجابة النموذجية الصحيحة', 'الخيار الثاني الخطأ تماماً', 'الخيار الثالث الخطأ للتمويه', 'الخيار الرابع الخطأ'],
                    'correct_idx' => 0,
                ],
                [
                    'question_text' => 'السؤال الثاني: أي من الأساليب التالية يمثل التطبيق العملي الصحيح لقوانين المادة؟',
                    'options' => ['خيار خاطئ أ', 'الإجابة النموذجية الصحيحة', 'خيار خاطئ ب', 'خيار خاطئ ج'],
                    'correct_idx' => 1,
                ],
                [
                    'question_text' => 'السؤال الثالث: ما هي النتيجة المباشرة المترتبة على التجربة الموصوفة في الدرس الثالث؟',
                    'options' => ['الاحتمال الأول غير الصحيح', 'الاحتمال الثاني غير الصحيح', 'الإجابة النموذجية الصحيحة', 'الاحتمال الرابع غير الصحيح'],
                    'correct_idx' => 2,
                ],
            ];

            // 50% chance to add a fourth question
            if (rand(0, 1)) {
                $questions[] = [
                    'question_text' => 'السؤال الرابع: أي العبارات التالية تلخص بشكل صحيح الاستنتاج النهائي للموضوع؟',
                    'options' => ['الخلاصة الخاطئة الأولى', 'الخلاصة الخاطئة الثانية', 'الخلاصة الخاطئة الثالثة', 'الإجابة النموذجية الصحيحة'],
                    'correct_idx' => 3,
                ];
            }

            foreach ($questions as $qIdx => $qData) {
                $question = QuizQuestion::create([
                    'quiz_id'       => $quiz->id,
                    'question_text' => $qData['question_text'],
                    'type'          => 'single',
                    'order'         => $qIdx + 1,
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

        // ─── 12. Worksheets (1 per course) ────────────────────────────
        $this->command->info('📂 Seeding worksheets...');
        foreach ($courseModels as $course) {
            Worksheet::create([
                'course_id'           => $course->id,
                'title'               => "أوراق عمل وتطبيقات منزلية لـ: {$course->title}",
                'file_path'           => '/storage/seeded-worksheets/worksheet_' . $course->id . '.pdf',
                'type'                => 'homework',
                'requires_submission' => true,
                'max_score'           => 100,
                'due_date'            => now()->addDays(15)->toDateString(),
            ]);
        }

        // ─── 13. Coupons ─────────────────────────────────────────────
        $this->command->info('🎟️ Seeding coupons...');
        $coupons = [
            ['code' => 'TAFAWWUQ10', 'discount_percent' => 10, 'is_active' => true, 'usage_limit' => 200, 'used_count' => 0],
            ['code' => 'TAFAWWUQ20', 'discount_percent' => 20, 'is_active' => true, 'usage_limit' => 100, 'used_count' => 0],
            ['code' => 'SUPER30',     'discount_percent' => 30, 'is_active' => true, 'usage_limit' => 50,  'used_count' => 0],
            ['code' => 'FREE100',     'discount_percent' => 100,'is_active' => true, 'usage_limit' => 15,  'used_count' => 0],
            ['code' => 'EXPIRED50',   'discount_percent' => 50, 'is_active' => false,'usage_limit' => 10,  'used_count' => 10, 'expires_at' => now()->subDays(1)],
        ];
        foreach ($coupons as $cp) {
            Coupon::create($cp);
        }
        $activeCoupons = Coupon::where('is_active', true)->get();

        // ─── 14. Enrollments, Progress, Payments & Invoices ───────────
        $this->command->info('📈 Seeding 220+ enrollments, progress records, payments & invoices...');
        
        $enrollmentCount = 0;
        $gateways = ['stripe', 'fatora', 'skipcash'];
        
        // Loop through students and enroll them in relevant courses
        foreach ($studentModels as $student) {
            $studentGrade = $student->grade_level;
            
            // Find courses matching this student's grade level, or general courses ('all')
            $availableCourses = Course::where(function($query) use ($studentGrade) {
                $query->where('grade_level', $studentGrade)
                      ->orWhere('grade_level', 'all');
            })->get();

            if ($availableCourses->isEmpty()) {
                // Fallback to random courses if none matched
                $availableCourses = Course::inRandomOrder()->take(5)->get();
            }

            // Enroll the student in 3 to 6 courses randomly
            $numberOfCoursesToEnroll = rand(3, 6);
            $coursesToEnroll = $availableCourses->random(min($numberOfCoursesToEnroll, $availableCourses->count()));

            foreach ($coursesToEnroll as $course) {
                $enrolledAt = now()->subDays(rand(2, 30));
                
                // Random progress distribution
                $progressPercent = rand(0, 4) * 25; // 0%, 25%, 50%, 75%, 100%
                $isCompleted = ($progressPercent === 100);
                $completedAt = $isCompleted ? (clone $enrolledAt)->addDays(rand(1, 10)) : null;

                $enrollment = Enrollment::create([
                    'user_id'          => $student->id,
                    'course_id'        => $course->id,
                    'progress_percent' => $progressPercent,
                    'enrolled_at'      => $enrolledAt,
                    'completed_at'     => $completedAt,
                ]);
                $enrollmentCount++;

                // Seed lesson progress based on progress percentage
                $lessons = $course->lessons;
                $totalLessons = $lessons->count();
                $completedLessonsCount = intval(($progressPercent / 100) * $totalLessons);

                foreach ($lessons as $lIdx => $lesson) {
                    if ($lIdx < $completedLessonsCount) {
                        // Mark as completed
                        LessonProgress::create([
                            'enrollment_id'   => $enrollment->id,
                            'lesson_id'       => $lesson->id,
                            'watched_seconds' => $lesson->duration_seconds,
                            'is_completed'    => true,
                        ]);
                    } elseif ($lIdx === $completedLessonsCount && $progressPercent > 0 && !$isCompleted) {
                        // Mark one lesson in progress
                        LessonProgress::create([
                            'enrollment_id'   => $enrollment->id,
                            'lesson_id'       => $lesson->id,
                            'watched_seconds' => rand(60, $lesson->duration_seconds - 60),
                            'is_completed'    => false,
                        ]);
                    }
                }

                // If course is paid, generate a payment record
                if ($course->price > 0) {
                    $originalAmount = $course->price;
                    $discountPrice = $course->discount_price;
                    
                    // 20% chance of using a coupon
                    $appliedCoupon = null;
                    $amount = $discountPrice ?? $originalAmount;
                    
                    if (rand(1, 5) === 1 && $activeCoupons->isNotEmpty()) {
                        $appliedCoupon = $activeCoupons->random();
                        $amount = intval($amount * (1 - ($appliedCoupon->discount_percent / 100)));
                        $appliedCoupon->increment('used_count');
                    }

                    $status = (rand(1, 20) <= 18) ? 'paid' : ((rand(1, 2) === 1) ? 'pending' : 'failed');
                    $paidAt = ($status === 'paid') ? $enrolledAt : null;

                    $payment = Payment::create([
                        'user_id'         => $student->id,
                        'course_id'       => $course->id,
                        'coupon_id'       => $appliedCoupon?->id,
                        'amount'          => $amount,
                        'original_amount' => $originalAmount,
                        'currency'        => 'QAR',
                        'gateway'         => $gateways[array_rand($gateways)],
                        'gateway_ref'     => 'tx_' . Str::random(12),
                        'status'          => $status,
                        'paid_at'         => $paidAt,
                    ]);

                    // If paid, create invoice
                    if ($status === 'paid') {
                        Invoice::create([
                            'payment_id'     => $payment->id,
                            'invoice_number' => 'INV-2026-' . str_pad((string)$payment->id, 6, '0', STR_PAD_LEFT),
                            'pdf_path'       => '/storage/invoices/inv_' . $payment->id . '.pdf',
                            'issued_at'      => $paidAt,
                        ]);
                    }
                }
            }
        }
        $this->command->info("   Successfully enrolled students ({$enrollmentCount} enrollments created).");

        // ─── 15. Reviews (120+ reviews) ────────────────────────────────
        $this->command->info('⭐ Seeding 120+ student reviews for courses...');
        $reviewComments = [
            'شرح ممتاز جداً ومبسط، بارك الله في مجهوداتكم!',
            'الدورة ساعدتني كثيراً في فهم النحو وإعراب الجمل المعقدة بسهولة.',
            'المعلم رائع جداً ومتعاون ويجيب على كافة الاستفسارات بوضوح.',
            'المحمد والتمارين والواجبات ممتازة جداً لمراجعة الدروس.',
            'من أفضل الكورسات التي سجلت بها على الإطلاق، ترتيب منطقي وسلس.',
            'جزاكم الله خيراً، الشرح واضح جداً والرسومات التوضيحية ممتازة.',
            'كورس رائع لتبسيط الكيمياء العضوية، أصبحت المعادلات سهلة للغاية بالنسبة لي.',
            'شرح مبسط وجاذب للاهتمام، طريقة التدريس مبتكرة ومختلفة تماماً عن المدرسة.',
            'أنصح جميع زملائي الطلاب بالتسجيل في هذا الكورس الرائع والمتميز.',
            'الملخصات وأوراق العمل المرفقة مفيدة للغاية ومجهزة بشكل احترافي.',
            'تغطية شاملة لكل أجزاء المنهج المدرسي مع مراجعات ممتازة للامتحان النهائي.',
            'كورس رائع ومنظم، يستحق كل دقيقة وكل قيمة دفعت فيه.',
        ];

        $approvedReviewsCount = 0;
        // Find enrollments with progress >= 25% and create reviews
        $activeEnrollments = Enrollment::where('progress_percent', '>=', 25)->inRandomOrder()->take(130)->get();
        foreach ($activeEnrollments as $enrollment) {
            // Ensure unique review per user and course
            $exists = Review::where('user_id', $enrollment->user_id)
                            ->where('course_id', $enrollment->course_id)
                            ->exists();
            if (!$exists) {
                Review::create([
                    'user_id'     => $enrollment->user_id,
                    'course_id'   => $enrollment->course_id,
                    'rating'      => rand(3, 5), // mostly 4-5 stars
                    'comment'     => $reviewComments[array_rand($reviewComments)],
                    'is_approved' => true,
                ]);
                $approvedReviewsCount++;
            }
        }
        $this->command->info("   Created {$approvedReviewsCount} approved reviews.");

        // ─── 16. Quiz Attempts (100+ attempts) ────────────────────────
        $this->command->info('📝 Seeding 100+ quiz attempts...');
        $attemptsCount = 0;
        
        $progressEnrollments = Enrollment::where('progress_percent', '>=', 50)->inRandomOrder()->take(120)->get();
        foreach ($progressEnrollments as $enrollment) {
            $quiz = Quiz::where('course_id', $enrollment->course_id)->first();
            if ($quiz) {
                $score = rand(40, 100);
                $passed = ($score >= $quiz->passing_score);
                // 15% chance of a violation
                $violations = (rand(1, 100) > 85) ? rand(1, 2) : 0;
                
                $startedAt = (clone $enrollment->enrolled_at)->addDays(rand(1, 5));
                $submittedAt = (clone $startedAt)->addMinutes(rand(10, 30));

                QuizAttempt::create([
                    'user_id'      => $enrollment->user_id,
                    'quiz_id'      => $quiz->id,
                    'score'        => $score,
                    'passed'       => $passed,
                    'started_at'   => $startedAt,
                    'submitted_at' => $submittedAt,
                    'violations'   => $violations,
                ]);
                $attemptsCount++;
            }
        }
        $this->command->info("   Created {$attemptsCount} quiz attempts.");

        // ─── 17. Worksheet Submissions (60+ submissions) ──────────────
        $this->command->info('📤 Seeding 60+ worksheet submissions...');
        $submissionsCount = 0;
        $teacherFeedbacks = [
            'عمل ممتاز ومجهود رائع يستحق التقدير، أحسنت!',
            'إجابات صحيحة ودقيقة، يرجى فقط الانتباه للمسألة الأخيرة.',
            'أحسنت صنعاً، إجابات نموذجية تظهر فهمك الممتاز للدرس.',
            'عمل جيد جداً، ولكن راجع شرح قوانين الحركة في الدرس الثالث.',
            'ممتاز، خط وتنسيق واضح وإجابات متكاملة.',
        ];

        $worksheetEnrollments = Enrollment::where('progress_percent', '>=', 50)->inRandomOrder()->take(75)->get();
        foreach ($worksheetEnrollments as $enrollment) {
            $worksheet = Worksheet::where('course_id', $enrollment->course_id)->first();
            if ($worksheet) {
                $submittedAt = (clone $enrollment->enrolled_at)->addDays(rand(1, 6));
                
                // 80% chance of being graded
                $isGraded = (rand(1, 10) <= 8);
                $score = $isGraded ? rand(70, 100) : null;
                $feedback = $isGraded ? $teacherFeedbacks[array_rand($teacherFeedbacks)] : null;
                $gradedAt = $isGraded ? (clone $submittedAt)->addDays(rand(1, 3)) : null;

                WorksheetSubmission::create([
                    'worksheet_id'        => $worksheet->id,
                    'student_id'          => $enrollment->user_id,
                    'submitted_file_path' => '/storage/submissions/student_' . $enrollment->user_id . '_worksheet_' . $worksheet->id . '.pdf',
                    'score'               => $score,
                    'teacher_feedback'    => $feedback,
                    'submitted_at'        => $submittedAt,
                    'graded_at'           => $gradedAt,
                ]);
                $submissionsCount++;
            }
        }
        $this->command->info("   Created {$submissionsCount} worksheet submissions.");

        // ─── 18. Live Sessions (30+ live sessions) ───────────────────
        $this->command->info('🎥 Seeding 30+ live sessions with attendee history...');
        $liveSessionTitles = [
            'جلسة تفاعلية مباشرة للإجابة على الأسئلة الصعبة',
            'مراجعة سريعة وحل أهم مسائل الامتحانات',
            'لقاء مباشر لمناقشة أوراق العمل والتقييمات',
            'جلسة إرشادية حول كيفية الاستعداد للاختبار النهائي',
            'مناقشة مفتوحة مع المعلم وتطبيقات عملية',
        ];

        $liveSessionCount = 0;
        foreach ($courseModels as $courseIdx => $course) {
            // Create 1-2 sessions per course
            $sessionsNum = rand(1, 2);
            for ($s = 1; $s <= $sessionsNum; $s++) {
                $status = 'ended';
                $scheduledAt = now()->subDays(rand(2, 20));
                $startedAt = clone $scheduledAt;
                $endedAt = (clone $startedAt)->addMinutes(60);

                if ($courseIdx === 0 && $s === 1) {
                    $status = 'live';
                    $scheduledAt = now()->subMinutes(15);
                    $startedAt = now()->subMinutes(15);
                    $endedAt = null;
                } elseif ($courseIdx === 1 && $s === 1) {
                    $status = 'scheduled';
                    $scheduledAt = now()->addDays(rand(1, 5));
                    $startedAt = null;
                    $endedAt = null;
                }

                $session = LiveSession::create([
                    'course_id'              => $course->id,
                    'teacher_id'             => $course->teacher_id,
                    'title'                  => $liveSessionTitles[array_rand($liveSessionTitles)] . ' - ' . $course->title,
                    'description'            => 'بث مباشر تفاعلي يهدف لمساعدة الطلاب والتواصل المباشر معهم لحل الأسئلة الصعبة.',
                    'scheduled_at'           => $scheduledAt,
                    'started_at'             => $startedAt,
                    'ended_at'               => $endedAt,
                    'status'                 => $status,
                    'room_id'                => 'room_' . Str::random(10),
                    'recording_url'          => ($status === 'ended') ? 'https://example.com/recordings/' . Str::random(8) : null,
                    'is_published_as_lesson' => false,
                    'lesson_id'              => null,
                ]);
                $liveSessionCount++;

                // Seed attendees for ended/live sessions
                if ($status === 'ended' || $status === 'live') {
                    // Get randomly enrolled students for this course
                    $enrolledStudentIds = Enrollment::where('course_id', $course->id)->pluck('user_id')->toArray();
                    if (!empty($enrolledStudentIds)) {
                        $attendeesCount = rand(2, min(8, count($enrolledStudentIds)));
                        $attendeeKeys = (array) array_rand($enrolledStudentIds, $attendeesCount);
                        foreach ($attendeeKeys as $key) {
                            $joinedAt = $startedAt ? (clone $startedAt)->addMinutes(rand(1, 10)) : null;
                            $leftAt = ($status === 'ended' && $joinedAt) ? (clone $joinedAt)->addMinutes(rand(30, 50)) : null;

                            DB::table('live_session_attendees')->insert([
                                'live_session_id' => $session->id,
                                'user_id'         => $enrolledStudentIds[$key],
                                'joined_at'       => $joinedAt,
                                'left_at'         => $leftAt,
                                'created_at'      => now(),
                                'updated_at'      => now(),
                            ]);
                        }
                    }
                }
            }
        }
        $this->command->info("   Created {$liveSessionCount} live sessions.");

        // ─── 19. Conversations & Messages (40+ conversations) ─────────
        $this->command->info('💬 Seeding 40+ active chat history conversations...');
        
        $convTemplates = [
            [
                ['sender' => 'student', 'msg' => 'السلام عليكم يا أستاذ، عندي سؤال بخصوص الدرس الثاني.'],
                ['sender' => 'teacher', 'msg' => 'وعليكم السلام ورحمة الله، تفضل يا بني ما هو سؤالك؟'],
                ['sender' => 'student', 'msg' => 'لم أفهم جيداً كيفية حل المسألة الخاصة بقوانين التناسب الطردي والعكسي.'],
                ['sender' => 'teacher', 'msg' => 'سؤال ممتاز! الفكرة تكمن في معرفة العلاقة: إذا زاد المتغير الأول وزاد الآخر معه فهو طردي، وإذا نقص فهو عكسي. سأرفع لك ملفاً إضافياً يوضح ذلك.'],
                ['sender' => 'student', 'msg' => 'شكراً جزيلاً يا أستاذ، اتضحت الفكرة تماماً الآن.'],
                ['sender' => 'teacher', 'msg' => 'بالتوفيق دائماً، لا تتردد في طرح أي أسئلة أخرى.'],
            ],
            [
                ['sender' => 'student', 'msg' => 'مرحبا أستاذ، هل هناك مراجعة مباشرة قبل امتحان الأسبوع القادم؟'],
                ['sender' => 'teacher', 'msg' => 'أهلاً بك، نعم بالتأكيد. قمت بجدولة جلسة بث مباشر يوم الأربعاء القادم لمراجعة المنهج بالكامل.'],
                ['sender' => 'student', 'msg' => 'رائع جداً! هل ستقوم بتسجيل الجلسة لمن لا يستطيع الحضور؟'],
                ['sender' => 'teacher', 'msg' => 'نعم، البث المباشر سيسجل تلقائياً وسيكون متاحاً للمشاهدة في أي وقت عبر صفحة الكورس.'],
                ['sender' => 'student', 'msg' => 'شكراً لك يا معلمي الفاضل.'],
            ],
            [
                ['sender' => 'student', 'msg' => 'أستاذي، لقد قمت برفع واجب الدرس الرابع، هل يمكنك مراجعته؟'],
                ['sender' => 'teacher', 'msg' => 'أهلاً بك يا بني. سأقوم بتصحيح جميع الواجبات المرفوعة اليوم وستصلك الدرجة مع الملاحظات في صفحة الواجبات.'],
                ['sender' => 'student', 'msg' => 'شكراً لاهتمامك ومساعدتك المستمرة لنا.'],
            ]
        ];

        $conversationsCreated = 0;
        // Pick random students who are enrolled in random courses
        $enrollmentList = Enrollment::inRandomOrder()->take(45)->get();
        foreach ($enrollmentList as $enrollment) {
            $course = $enrollment->course;
            $student = $enrollment->user;
            $teacher = $course->teacher;

            // Ensure unique conversation
            $exists = Conversation::where('course_id', $course->id)
                                  ->where('student_id', $student->id)
                                  ->where('teacher_id', $teacher->id)
                                  ->exists();

            if (!$exists) {
                $conv = Conversation::create([
                    'course_id'       => $course->id,
                    'student_id'      => $student->id,
                    'teacher_id'      => $teacher->id,
                    'last_message_at' => now()->subMinutes(rand(10, 1000)),
                ]);
                $conversationsCreated++;

                // Seed messages back and forth
                $chatTemplate = $convTemplates[array_rand($convTemplates)];
                foreach ($chatTemplate as $msgIdx => $m) {
                    $senderId = ($m['sender'] === 'student') ? $student->id : $teacher->id;
                    ChatMessage::create([
                        'conversation_id' => $conv->id,
                        'sender_id'       => $senderId,
                        'message'         => $m['msg'],
                        'is_read'         => ($msgIdx < count($chatTemplate) - 1) ? true : (rand(0, 1) === 1),
                        'created_at'      => (clone $conv->last_message_at)->subMinutes((count($chatTemplate) - $msgIdx) * 10),
                    ]);
                }
            }
        }
        $this->command->info("   Created {$conversationsCreated} active student-teacher conversation threads.");

        // ─── 20. Teacher Payouts (12 payout history logs) ─────────────
        $this->command->info('💰 Seeding teacher payout logs...');
        foreach ($teacherModels as $tName => $teacher) {
            // Pick courses for this teacher
            $courseIds = $teacher->coursesAsTeacher->pluck('id')->toArray();
            
            // Get successful payments sum for this teacher's courses
            $totalSales = Payment::whereIn('course_id', $courseIds)
                                 ->where('status', 'paid')
                                 ->sum('amount');
            
            if ($totalSales > 0) {
                // Seed a payout record for last month
                $commissionPercent = 20; // platform commission
                $payoutAmount = intval($totalSales * (1 - ($commissionPercent / 100)));
                
                TeacherPayout::create([
                    'teacher_id'          => $teacher->id,
                    'amount'              => $payoutAmount,
                    'platform_commission' => $commissionPercent,
                    'period_start'        => now()->subMonth()->startOfMonth()->toDateString(),
                    'period_end'          => now()->subMonth()->endOfMonth()->toDateString(),
                    'status'              => 'paid',
                    'paid_at'             => now()->subDays(5),
                    'notes'               => "تحويل مستحقات المعلم لشهر " . now()->subMonth()->translatedFormat('F') . " 2026 بعد خصم عمولة المنصة البالغة {$commissionPercent}%",
                ]);
            }
        }

        // ─── 21. Platform Settings ────────────────────────────────────
        $this->command->info('⚙️ Making sure platform settings are up to date...');
        DB::table('platform_settings')->updateOrInsert(
            ['key' => 'commission_percent'],
            ['value' => '20', 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('platform_settings')->updateOrInsert(
            ['key' => 'platform_name'],
            ['value' => 'منصة التفوق', 'created_at' => now(), 'updated_at' => now()]
        );
        // ─── 22. Purchase Requests ────────────────────────────────────
        $this->command->info('💳 Seeding purchase requests (pending, approved, rejected)...');
        $links = ParentStudentLink::all();
        $courses = Course::where('price', '>', 0)->get();

        $purchaseRequestsCount = 0;
        foreach ($links->take(10) as $idx => $link) {
            $enrolledCourseIds = Enrollment::where('user_id', $link->student_user_id)->pluck('course_id')->toArray();
            $availableCourse = $courses->whereNotIn('id', $enrolledCourseIds)->first();

            if ($availableCourse) {
                $status = 'pending';
                $notes = null;

                if ($idx >= 4 && $idx <= 6) {
                    $status = 'approved';
                } elseif ($idx >= 7) {
                    $status = 'rejected';
                    $notes = 'هذا الكورس لا يتناسب مع مستواك الدراسي الحالي، يرجى استشارة مدرسك.';
                }

                $pr = PurchaseRequest::create([
                    'student_user_id' => $link->student_user_id,
                    'parent_user_id'  => $link->parent_user_id,
                    'course_id'       => $availableCourse->id,
                    'status'          => $status,
                    'notes'           => $notes,
                ]);

                if ($status === 'approved') {
                    $pay = Payment::create([
                        'user_id'         => $link->student_user_id,
                        'course_id'       => $availableCourse->id,
                        'amount'          => $availableCourse->getEffectivePrice(),
                        'original_amount' => $availableCourse->price,
                        'currency'        => 'QAR',
                        'gateway'         => 'manual',
                        'gateway_ref'     => 'سداد ولي الأمر من المحفظة',
                        'status'          => 'paid',
                        'paid_at'         => now()->subDays(1),
                        'purchase_request_id' => $pr->id,
                    ]);

                    Enrollment::firstOrCreate(
                        ['user_id' => $link->student_user_id, 'course_id' => $availableCourse->id],
                        ['enrolled_at' => now()->subDays(1), 'progress_percent' => 0]
                    );

                    Invoice::create([
                        'payment_id'     => $pay->id,
                        'invoice_number' => "INV-" . now()->year . "-" . str_pad((string)$pay->id, 6, '0', STR_PAD_LEFT),
                        'issued_at'      => now()->subDays(1),
                    ]);
                }

                $purchaseRequestsCount++;
            }
        }
        $this->command->info("   Created {$purchaseRequestsCount} purchase requests.");

        // ─── 23. Lesson Questions (Q&A Forum) ───────────────────────────
        $this->command->info('💬 Seeding lesson Q&A questions and replies...');
        $enrollments = Enrollment::with('course.lessons', 'user')->get();
        $questionsCount = 0;

        $sampleQuestions = [
            ['content' => 'لم أفهم النقطة المتعلقة بالقانون المشروح هنا تماماً، هل يمكن إعادة تبسيطها؟', 'timestamp' => 125],
            ['content' => 'هل هذا الجزء مطلوب معنا في الاختبار النهائي للمرحلة؟', 'timestamp' => 310],
            ['content' => 'هناك خطأ مطبعي بسيط في كتابة المعادلة على السبورة عند هذه الدقيقة.', 'timestamp' => 84],
            ['content' => 'الرجاء توضيح أين أجد شيت الواجب الخاص بهذه المحاضرة؟', 'timestamp' => null],
            ['content' => 'مستوى الشرح رائع جداً وجزاك الله خيراً يا أستاذنا القدير!', 'timestamp' => null],
        ];

        $sampleAnswers = [
            'أهلاً بك يا بطل، قمنا بتبسيطها في ورقة العمل المرفقة بالدرس، يرجى مراجعتها.',
            'نعم، هذا المحتوى أساسي ومطلوب جداً في الاختبار النهائي، ركز عليه.',
            'أحسنت الملاحظة! سيتم تعديلها وتنبيه بقية الطلاب، شكراً لاهتمامك ودقتك.',
            'شيت الواجب موجود تحت تبويب "الملفات والواجبات" أسفل الفيديو مباشرة.',
            'وفقك الله وسدد خطاك، يسعدني جداً سماع هذا الكلام الجميل!',
        ];

        foreach ($enrollments->take(8) as $idx => $enroll) {
            $lessons = $enroll->course->lessons;
            if ($lessons->isEmpty()) continue;

            $lesson = $lessons->first();

            $qTemplate = $sampleQuestions[$idx % count($sampleQuestions)];

            $q = LessonQuestion::create([
                'user_id'         => $enroll->user_id,
                'lesson_id'       => $lesson->id,
                'content'         => $qTemplate['content'],
                'video_timestamp' => $qTemplate['timestamp'],
                'created_at'      => now()->subDays(rand(1, 5)),
            ]);

            if ($enroll->course->teacher_id) {
                LessonQuestion::create([
                    'user_id'         => $enroll->course->teacher_id,
                    'lesson_id'       => $lesson->id,
                    'parent_id'       => $q->id,
                    'content'         => $sampleAnswers[$idx % count($sampleAnswers)],
                    'video_timestamp' => null,
                    'created_at'      => $q->created_at->addHours(rand(1, 12)),
                ]);
            }
            $questionsCount++;
        }
        $this->command->info("   Created {$questionsCount} lesson questions with teacher replies.");

        $this->command->info('🥇 Seeding process completed successfully!');
        $this->command->info('   - Admin:     admin@altafawwuq.com / password');
        $this->command->info('   - Teacher 1: teacher.ahmed@altafawwuq.com / password');
        $this->command->info('   - Student 1: student1@altafawwuq.com / password');
        $this->command->info('   - Parent 1:  parent1@altafawwuq.com / password');
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

        // Convert bgColorHex to RGB or fallback to random
        $r_bg = 122; $g_bg = 28; $b_bg = 55;
        if (preg_match('/^#?([a-f0-9]{2})([a-f0-9]{2})([a-f0-9]{2})$/i', $bgColorHex, $parts)) {
            $r_bg = hexdec($parts[1]);
            $g_bg = hexdec($parts[2]);
            $b_bg = hexdec($parts[3]);
        }

        // Gradient background
        $startR = max(0, $r_bg - 30);  $startG = max(0, $g_bg - 20);   $startB = max(0, $b_bg - 10);
        $endR   = min(255, $r_bg + 40);  $endG   = min(255, $g_bg + 40);  $endB   = min(255, $b_bg + 50);

        for ($x = 0; $x < $width; $x++) {
            $r = intval($startR + ($endR - $startR) * ($x / $width));
            $g = intval($startG + ($endG - $startG) * ($x / $width));
            $b = intval($startB + ($endB - $startB) * ($x / $width));
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, $x, 0, $x, $height, $color);
        }

        // Draw soft glowing ambient circles with alpha transparency
        imagealphablending($image, true);
        
        // Aura 1: Gold/Yellow glow
        $goldGlow = imagecolorallocatealpha($image, 212, 175, 55, 105);
        imagefilledellipse($image, intval($width * 0.8), intval($height * 0.3), intval($width * 0.7), intval($height * 0.7), $goldGlow);

        // Aura 2: Deep blue/purple glow
        $darkGlow = imagecolorallocatealpha($image, 40, 20, 100, 110);
        imagefilledellipse($image, intval($width * 0.2), intval($height * 0.7), intval($width * 0.8), intval($height * 0.8), $darkGlow);

        // Add text using basic GD font
        $textColor = imagecolorallocate($image, 255, 255, 255);
        
        // Draw text near center (using built-in GD font 5)
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        
        $x_pos = intval(($width - $textWidth) / 2);
        $y_pos = intval(($height - $textHeight) / 2);
        
        // Draw dark shadow first
        $shadowColor = imagecolorallocate($image, 0, 0, 0);
        imagestring($image, $font, $x_pos + 1, $y_pos + 1, $text, $shadowColor);
        imagestring($image, $font, $x_pos, $y_pos, $text, $textColor);

        $filename = uniqid() . '.webp';
        $path = $dir . '/' . $filename;
        imagewebp($image, $path, 90);
        imagedestroy($image);

        return '/storage/' . $folder . '/' . $filename;
    }
}
