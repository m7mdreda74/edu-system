<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Communication\Notifications\AcademicAlertNotification;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LearningResource;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionApology;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Quiz\Models\QuizOption;
use App\Domain\Quiz\Models\QuizQuestion;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\TeachingGroupLesson;
use App\Domain\Scheduling\Models\TeachingGroupSchedule;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class ComprehensiveDemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        $this->command?->info('🚀 بدء إنشاء وتجهيز البيانات الشاملة للمنصة...');

        // 1. Roles
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('teacher', 'web');
        Role::findOrCreate('student', 'web');
        Role::findOrCreate('parent', 'web');

        $passwordHash = Hash::make(self::PASSWORD);
        $now = now();

        // Helper to safely create or update users without phone collisions
        $upsertUser = function (string $email, string $name, string $phone, array $extra = []) use ($passwordHash, $now): User {
            $user = User::firstOrNew(['email' => $email]);
            $user->name = $name;
            if (! $user->exists || empty($user->phone)) {
                $user->phone = $phone;
            }
            $user->password = $passwordHash;
            $user->email_verified_at = $now;
            $user->is_active = true;
            foreach ($extra as $key => $value) {
                $user->{$key} = $value;
            }
            $user->save();

            return $user;
        };

        // 2. Admins
        $admin = $upsertUser('admin@almagd.com', 'مدير النظام (أدمن)', '+97455000001');
        $admin->syncRoles(['admin']);

        // 3. Subjects & Grade Level
        $math = Subject::firstOrCreate(['name' => 'الرياضيات'], ['code' => 'MATH', 'order' => 1]);
        $physics = Subject::firstOrCreate(['name' => 'الفيزياء'], ['code' => 'PHY', 'order' => 2]);
        $english = Subject::firstOrCreate(['name' => 'اللغة الإنجليزية'], ['code' => 'ENG', 'order' => 3]);

        $grade10 = GradeLevel::where('name', 'الصف العاشر')->first()
            ?? GradeLevel::where('name', 'الصف الأول الثانوي')->first()
            ?? GradeLevel::firstOrCreate(
                ['name' => 'الصف العاشر'],
                ['key' => 'grade_10', 'order' => 10, 'is_active' => true]
            );

        // Ensure Vodafone cash number for testing payments
        if (Schema::hasColumn('grade_levels', 'vodafone_cash_number') && empty($grade10->vodafone_cash_number)) {
            $grade10->update(['vodafone_cash_number' => '01012345678']);
        }

        $term = AcademicTerm::currentOrNext() ?? AcademicTerm::firstOrCreate(
            ['name' => 'الفصل الدراسي الأول 2026-2027'],
            [
                'starts_at' => $now->copy()->startOfYear(),
                'ends_at' => $now->copy()->endOfYear(),
                'is_current' => true,
            ]
        );

        // 4. Teachers
        $teacherMath = $upsertUser('teacher@almagd.com', 'أ. أحمد منصور (رياضيات)', '+97455000003', [
            'is_featured' => true,
            'headline' => 'كبير معلمي الرياضيات والقدرات الثانوية',
            'bio' => 'معلم أول لمادة الرياضيات بخبرة 12 عاماً في المناهج وتبسيط المفاهيم الصعبة.',
            'years_experience' => 12,
            'subject_id' => $math->id,
        ]);
        $teacherMath->syncRoles(['teacher']);

        $teacherPhysics = $upsertUser('tariq@almagd.com', 'أ. طارق الشمري (فيزياء)', '+97455000011', [
            'is_featured' => true,
            'headline' => 'أستاذ الفيزياء والعلوم العامة',
            'bio' => 'متخصص في شرح الفيزياء النظرية والتطبيقية والتجارب العملية.',
            'years_experience' => 9,
            'subject_id' => $physics->id,
        ]);
        $teacherPhysics->syncRoles(['teacher']);

        $teacherEnglish = $upsertUser('sara@almagd.com', 'أ. سارة الكواري (إنجليزي)', '+97455000012', [
            'is_featured' => true,
            'headline' => 'معلمة اللغة الإنجليزية المعتمدة',
            'bio' => 'تدريس مهارات اللغة والتأسيس الأكاديمي واختبارات القبول.',
            'years_experience' => 8,
            'subject_id' => $english->id,
        ]);
        $teacherEnglish->syncRoles(['teacher']);

        // 5. Teaching Assignments
        $mathAssignment = TeachingAssignment::updateOrCreate(
            ['teacher_id' => $teacherMath->id, 'subject_id' => $math->id, 'grade_level_id' => $grade10->id],
            ['accepts_private' => true, 'private_monthly_price' => 300, 'is_active' => true]
        );

        $physicsAssignment = TeachingAssignment::updateOrCreate(
            ['teacher_id' => $teacherPhysics->id, 'subject_id' => $physics->id, 'grade_level_id' => $grade10->id],
            ['accepts_private' => true, 'private_monthly_price' => 280, 'is_active' => true]
        );

        // 6. Teaching Groups & Schedules
        $mathGroup = TeachingGroup::updateOrCreate(
            ['teaching_assignment_id' => $mathAssignment->id, 'name' => 'مجموعة العباقرة — الرياضيات'],
            [
                'academic_term_id' => $term->id,
                'capacity' => 30,
                'monthly_price' => 200,
                'currency' => 'QAR',
                'day_of_week' => 6, // Saturday
                'start_time' => '19:00',
                'end_time' => '20:15',
                'duration_minutes' => 75,
                'timezone' => 'Asia/Qatar',
                'is_active' => true,
            ]
        );

        TeachingGroupSchedule::updateOrCreate(
            ['teaching_group_id' => $mathGroup->id, 'day_of_week' => 6],
            ['start_time' => '19:00', 'end_time' => '20:15', 'duration_minutes' => 75]
        );
        TeachingGroupSchedule::updateOrCreate(
            ['teaching_group_id' => $mathGroup->id, 'day_of_week' => 2],
            ['start_time' => '19:00', 'end_time' => '20:15', 'duration_minutes' => 75]
        );

        $physicsGroup = TeachingGroup::updateOrCreate(
            ['teaching_assignment_id' => $physicsAssignment->id, 'name' => 'مجموعة الرواد — الفيزياء'],
            [
                'academic_term_id' => $term->id,
                'capacity' => 25,
                'monthly_price' => 180,
                'currency' => 'QAR',
                'day_of_week' => 0, // Sunday
                'start_time' => '18:00',
                'end_time' => '19:15',
                'duration_minutes' => 75,
                'timezone' => 'Asia/Qatar',
                'is_active' => true,
            ]
        );

        // 7. Academic Units and Lessons (Syllabus Structure)
        // Unit 1 Math:
        $mathUnit1 = CurriculumUnit::updateOrCreate(
            ['teaching_assignment_id' => $mathAssignment->id, 'title' => 'الوحدة الأولى: الجبر والمعادلات'],
            [
                'academic_term_id' => $term->id,
                'order' => 1,
                'is_published' => true,
                'description' => 'شرح تأسيسي لحل المعادلات الخطية والتربيعية وأنظمة المعادلات.',
            ]
        );

        $mathLesson1 = GroupMaterial::updateOrCreate(
            ['curriculum_unit_id' => $mathUnit1->id, 'title' => 'الدرس الأول: مقدمة في المعادلات الخطية'],
            [
                'academic_term_id' => $term->id,
                'order' => 1,
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'duration_seconds' => 1800,
                'is_free_preview' => true,
                'description' => 'مفهوم المعادلات والمتباينات وطرق التبسيط الجبري.',
            ]
        );

        $mathLesson2 = GroupMaterial::updateOrCreate(
            ['curriculum_unit_id' => $mathUnit1->id, 'title' => 'الدرس الثاني: حل المعادلات متعددة الخطوات'],
            [
                'academic_term_id' => $term->id,
                'order' => 2,
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'duration_seconds' => 2400,
                'is_free_preview' => false,
                'description' => 'طرق عزل المتغير وحل المعادلات الكسرية والجذرية.',
            ]
        );

        $mathLesson3 = GroupMaterial::updateOrCreate(
            ['curriculum_unit_id' => $mathUnit1->id, 'title' => 'الدرس الثالث: أنظمة المعادلات الجبرية'],
            [
                'academic_term_id' => $term->id,
                'order' => 3,
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'duration_seconds' => 2100,
                'is_free_preview' => false,
                'description' => 'طريقة الحذف والتعويض في حل نظام من معادلتين خطيتين.',
            ]
        );

        $mathLesson4 = GroupMaterial::updateOrCreate(
            ['curriculum_unit_id' => $mathUnit1->id, 'title' => 'الدرس الرابع: تطبيقات ومسائل وزارية'],
            [
                'academic_term_id' => $term->id,
                'order' => 4,
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'duration_seconds' => 2700,
                'is_free_preview' => false,
                'description' => 'حل نماذج امتحانات السنوات السابقة على الجبر.',
            ]
        );

        // Unit 2 Math:
        $mathUnit2 = CurriculumUnit::updateOrCreate(
            ['teaching_assignment_id' => $mathAssignment->id, 'title' => 'الوحدة الثانية: الهندسة والتحليل الرياضي'],
            [
                'academic_term_id' => $term->id,
                'order' => 2,
                'is_published' => true,
                'description' => 'نظريات الدائرة، المماسات، وحساب المثلثات وتطبيقاتها.',
            ]
        );

        $mathLesson2_1 = GroupMaterial::updateOrCreate(
            ['curriculum_unit_id' => $mathUnit2->id, 'title' => 'الدرس الأول: نظريات المماسات والأوتار'],
            [
                'academic_term_id' => $term->id,
                'order' => 1,
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'duration_seconds' => 2000,
                'is_free_preview' => false,
                'description' => 'خصائص الأوتار والمماسات والزوايا المحيطية والمركزية.',
            ]
        );

        $mathLesson2_2 = GroupMaterial::updateOrCreate(
            ['curriculum_unit_id' => $mathUnit2->id, 'title' => 'الدرس الثاني: حساب المثلثات والزوايا الخاصة'],
            [
                'academic_term_id' => $term->id,
                'order' => 2,
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'duration_seconds' => 2200,
                'is_free_preview' => false,
                'description' => 'النسب المثلثية وقوانين الجيب وجيب التمام في المثلثات.',
            ]
        );

        // 8. Students and Parents
        $student1 = $upsertUser('student@almagd.com', 'عمر خالد (طالب متفوق)', '+97455000004', [
            'grade_level' => $grade10->key,
        ]);
        $student1->syncRoles(['student']);

        $student2 = $upsertUser('student2@almagd.com', 'فاطمة النعيمي (طالبة)', '+97455000013', [
            'grade_level' => $grade10->key,
        ]);
        $student2->syncRoles(['student']);

        $parent = $upsertUser('parent@almagd.com', 'خالد الباكر (ولي أمر عمر)', '+97455000005');
        $parent->syncRoles(['parent']);

        ParentStudentLink::updateOrCreate(
            ['parent_user_id' => $parent->id, 'student_user_id' => $student1->id],
            [
                'relationship' => 'father',
                'verified_at' => $now,
                'status' => ParentStudentLink::STATUS_APPROVED,
            ]
        );

        // 9. Active Subscriptions
        Subscription::updateOrCreate(
            ['student_id' => $student1->id, 'teaching_group_id' => $mathGroup->id],
            [
                'type' => Subscription::TYPE_GROUP,
                'teaching_assignment_id' => $mathAssignment->id,
                'monthly_price' => 200,
                'currency' => 'QAR',
                'period_start' => $now->copy()->subDays(10),
                'period_end' => $now->copy()->addDays(20),
                'status' => Subscription::STATUS_ACTIVE,
                'auto_renew' => true,
            ]
        );

        SessionBooking::updateOrCreate(
            ['student_id' => $student1->id, 'teaching_group_id' => $mathGroup->id],
            ['status' => 'confirmed', 'booked_at' => $now->copy()->subDays(10)]
        );

        // 10. Student Lesson Progress (Tracking progress)
        LessonProgress::updateOrCreate(
            ['student_id' => $student1->id, 'lesson_id' => $mathLesson1->id],
            ['watched_seconds' => 1800, 'is_completed' => true]
        );
        LessonProgress::updateOrCreate(
            ['student_id' => $student1->id, 'lesson_id' => $mathLesson2->id],
            ['watched_seconds' => 1900, 'is_completed' => false]
        );

        // 11. Worksheets with Deadlines & Submissions (Covering All Late/Overdue States)
        // HW 1: Submitted on time and graded
        $hw1 = Worksheet::updateOrCreate(
            ['curriculum_unit_id' => $mathUnit1->id, 'title' => 'واجب الدرس الأول: حل المعادلات البسيطة'],
            [
                'lesson_id' => $mathLesson1->id,
                'file_path' => 'private://worksheets/math_hw1.pdf',
                'type' => Worksheet::TYPE_HOMEWORK,
                'requires_submission' => true,
                'due_date' => $now->copy()->subDays(7),
                'max_score' => 20,
            ]
        );
        WorksheetSubmission::updateOrCreate(
            ['worksheet_id' => $hw1->id, 'student_id' => $student1->id],
            [
                'submitted_file_path' => 'private://submissions/omar_hw1.pdf',
                'submitted_at' => $now->copy()->subDays(8), // On time
                'is_late' => false,
                'score' => 19,
                'teacher_feedback' => 'أحسنت يا عمر، إجابة نموذجية وخطوات حل دقيقة ومتقنة.',
                'graded_at' => $now->copy()->subDays(7),
            ]
        );

        // HW 2: Submitted LATE (After deadline) -> Has is_late = true and badge
        $hw2 = Worksheet::updateOrCreate(
            ['curriculum_unit_id' => $mathUnit1->id, 'title' => 'واجب الدرس الثاني: حل المعادلات متعددة الخطوات'],
            [
                'lesson_id' => $mathLesson2->id,
                'file_path' => 'private://worksheets/math_hw2.pdf',
                'type' => Worksheet::TYPE_HOMEWORK,
                'requires_submission' => true,
                'due_date' => $now->copy()->subDays(4),
                'max_score' => 20,
            ]
        );
        WorksheetSubmission::updateOrCreate(
            ['worksheet_id' => $hw2->id, 'student_id' => $student1->id],
            [
                'submitted_file_path' => 'private://submissions/omar_hw2_late.pdf',
                'submitted_at' => $now->copy()->subDays(1), // Submitted 3 days late
                'is_late' => true,
                'score' => 16,
                'teacher_feedback' => 'حل جيد جداً، لكن تم خصم درجتين بسبب التأخر في تسليم الواجب عن الموعد المحدد.',
                'graded_at' => $now->copy()->subHours(12),
            ]
        );

        // HW 3: OVERDUE and NOT submitted yet -> Student receives alert notification
        $hw3 = Worksheet::updateOrCreate(
            ['curriculum_unit_id' => $mathUnit1->id, 'title' => 'واجب الدرس الثالث: أنظمة المعادلات'],
            [
                'lesson_id' => $mathLesson3->id,
                'file_path' => 'private://worksheets/math_hw3.pdf',
                'type' => Worksheet::TYPE_HOMEWORK,
                'requires_submission' => true,
                'due_date' => $now->copy()->subDays(2),
                'max_score' => 20,
            ]
        );

        // Notification for overdue homework
        $student1->notify(new AcademicAlertNotification([
            'alert_type' => 'missing_assignment',
            'title' => '⚠️ تنبيه: تأخرت في تسليم الواجب',
            'message' => "تأخرت في تسليم واجب {$hw3->title} في مادة الرياضيات وكان آخر موعد هو {$hw3->due_date->toDateString()}. يرجى إنجاز الواجب وتسليمه في أقرب وقت عبر مركز الواجبات.",
            'link' => route('student.assignments'),
            'parent_link' => route('parent.dashboard'),
            'icon' => 'warning',
            'student_id' => $student1->id,
            'entity_id' => $hw3->id,
        ]));

        // HW 4: Upcoming deadline (Active/Pending)
        $hw4 = Worksheet::updateOrCreate(
            ['curriculum_unit_id' => $mathUnit1->id, 'title' => 'واجب الدرس الرابع: النماذج الوزارية الشاملة'],
            [
                'lesson_id' => $mathLesson4->id,
                'file_path' => 'private://worksheets/math_hw4.pdf',
                'type' => Worksheet::TYPE_HOMEWORK,
                'requires_submission' => true,
                'due_date' => $now->copy()->addDays(5),
                'max_score' => 25,
            ]
        );

        // 12. Paper Exam for Unit 1
        Worksheet::updateOrCreate(
            ['curriculum_unit_id' => $mathUnit1->id, 'type' => Worksheet::TYPE_PAPER_EXAM],
            [
                'title' => 'اختبار نهاية الوحدة الأولى (النموذج المقالي والورقي)',
                'file_path' => 'private://exams/unit1_math_paper_exam.pdf',
                'requires_submission' => true,
                'due_date' => $now->copy()->addDays(10),
                'max_score' => 50,
            ]
        );

        // 13. Electronic Quizzes (Unit Exam & Comprehensive Exam)
        $unitQuiz = Quiz::updateOrCreate(
            ['curriculum_unit_id' => $mathUnit1->id, 'title' => 'اختبار الوحدة الأولى الإلكتروني — الجبر والمعادلات'],
            [
                'passing_score' => 70,
                'time_limit_minutes' => 30,
                'is_active' => true,
                'available_from' => $now->copy()->subDays(5),
                'available_until' => $now->copy()->addDays(20),
            ]
        );

        $q1 = QuizQuestion::updateOrCreate(
            ['quiz_id' => $unitQuiz->id, 'order' => 1],
            [
                'question_text' => 'ما هو حل المعادلة الخطية: 2س + 6 = 16 ؟',
                'type' => 'multiple_choice',
                'points' => 5,
            ]
        );
        QuizOption::updateOrCreate(['question_id' => $q1->id, 'option_text' => 'س = 5'], ['is_correct' => true]);
        QuizOption::updateOrCreate(['question_id' => $q1->id, 'option_text' => 'س = 3'], ['is_correct' => false]);
        QuizOption::updateOrCreate(['question_id' => $q1->id, 'option_text' => 'س = 8'], ['is_correct' => false]);
        QuizOption::updateOrCreate(['question_id' => $q1->id, 'option_text' => 'س = 10'], ['is_correct' => false]);

        $q2 = QuizQuestion::updateOrCreate(
            ['quiz_id' => $unitQuiz->id, 'order' => 2],
            [
                'question_text' => 'إذا كان س = 4، فما قيمة المقدار: 3س² - 2س + 1 ؟',
                'type' => 'multiple_choice',
                'points' => 5,
            ]
        );
        QuizOption::updateOrCreate(['question_id' => $q2->id, 'option_text' => '41'], ['is_correct' => true]);
        QuizOption::updateOrCreate(['question_id' => $q2->id, 'option_text' => '39'], ['is_correct' => false]);
        QuizOption::updateOrCreate(['question_id' => $q2->id, 'option_text' => '45'], ['is_correct' => false]);
        QuizOption::updateOrCreate(['question_id' => $q2->id, 'option_text' => '50'], ['is_correct' => false]);

        // Student attempt
        QuizAttempt::updateOrCreate(
            ['quiz_id' => $unitQuiz->id, 'user_id' => $student1->id],
            [
                'started_at' => $now->copy()->subDays(2),
                'submitted_at' => $now->copy()->subDays(2)->addMinutes(18),
                'score' => 100,
                'earned_points' => 10,
                'total_points' => 10,
                'passed' => true,
            ]
        );

        // Comprehensive Unit 2 Exam
        Quiz::updateOrCreate(
            ['curriculum_unit_id' => $mathUnit2->id, 'title' => 'اختبار شامل على الهندسة وحساب المثلثات'],
            [
                'passing_score' => 60,
                'time_limit_minutes' => 45,
                'is_active' => true,
                'available_from' => $now->copy()->subDays(1),
                'available_until' => $now->copy()->addDays(30),
            ]
        );

        // 14. Learning Resources (Review Workflow: Pending, Approved, Rejected)
        // 1. Pending resource (Waiting for Admin approval)
        $resPending = LearningResource::updateOrCreate(
            ['title' => 'ملزمة المراجعة النهائية في الجبر وتطبيقاته'],
            [
                'owner_teacher_id' => $teacherMath->id,
                'description' => 'ملزمة تلخيصية شاملة لكل قوانين الجبر وأهم المسائل المتوقعة.',
                'category' => 'booklet',
                'tags' => ['رياضيات', 'جبر', 'مراجعة'],
                'file_path' => 'resources/math_algebra_review.pdf',
                'original_name' => 'math_algebra_review.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 2048576,
                'visibility' => LearningResource::VISIBILITY_SHARED,
                'approval_status' => LearningResource::APPROVAL_PENDING,
                'is_active' => true,
            ]
        );
        $resPending->assignments()->syncWithoutDetaching([
            $mathAssignment->id => ['attached_by_teacher_id' => $teacherMath->id],
        ]);

        // 2. Approved resource (Live in Resource Library)
        $resApproved = LearningResource::updateOrCreate(
            ['title' => 'بنك أسئلة الرياضيات الوزارية مع نموذج الإجابة'],
            [
                'owner_teacher_id' => $teacherMath->id,
                'description' => 'أكثر من 150 سؤالاً وزارياً محلولاً ومفسراً خطوة بخطوة.',
                'category' => 'question_bank',
                'tags' => ['بنك_أسئلة', 'وزاري', 'نماذج_محلولة'],
                'file_path' => 'resources/math_question_bank_2026.pdf',
                'original_name' => 'math_question_bank_2026.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 3145728,
                'visibility' => LearningResource::VISIBILITY_SHARED,
                'approval_status' => LearningResource::APPROVAL_APPROVED,
                'approved_by_admin_id' => $admin->id,
                'approved_at' => $now->copy()->subDays(2),
                'admin_notes' => 'محتوى ممتاز ومطابق للمعايير الوزارية، تم الاعتماد.',
                'is_active' => true,
            ]
        );
        $resApproved->assignments()->syncWithoutDetaching([
            $mathAssignment->id => ['attached_by_teacher_id' => $teacherMath->id],
        ]);

        // 3. Rejected resource (Shows Admin feedback / rejection reason to Teacher)
        $resRejected = LearningResource::updateOrCreate(
            ['title' => 'مسودة اختبارات تجريبية غير منقحة'],
            [
                'owner_teacher_id' => $teacherMath->id,
                'description' => 'مسودة أولية لنماذج اختبارات قصيرة.',
                'category' => 'exam',
                'tags' => ['مسودة', 'اختبار'],
                'file_path' => 'resources/draft_tests.docx',
                'original_name' => 'draft_tests.docx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'size_bytes' => 1048576,
                'visibility' => LearningResource::VISIBILITY_SHARED,
                'approval_status' => LearningResource::APPROVAL_REJECTED,
                'admin_notes' => 'الملف يحتاج إلى تنسيق الصفحات وإضافة شعار المنصة وحلول الأسئلة قبل الاعتماد.',
                'rejection_reason' => 'الملف غير مكتمل التنسيق والإجابات.',
                'rejected_at' => $now->copy()->subDay(),
                'is_active' => true,
            ]
        );
        $resRejected->assignments()->syncWithoutDetaching([
            $mathAssignment->id => ['attached_by_teacher_id' => $teacherMath->id],
        ]);

        // 4. Approved Physics Resource
        $resPhysics = LearningResource::updateOrCreate(
            ['title' => 'دليل التجارب العملية وقوانين الميكانيكا'],
            [
                'owner_teacher_id' => $teacherPhysics->id,
                'description' => 'شرح مصور لقوانين نيوتن وحساب الشغل والطاقة.',
                'category' => 'booklet',
                'tags' => ['فيزياء', 'ميكانيكا', 'تجارب'],
                'file_path' => 'resources/physics_experiments_guide.pdf',
                'original_name' => 'physics_experiments_guide.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 4194304,
                'visibility' => LearningResource::VISIBILITY_SHARED,
                'approval_status' => LearningResource::APPROVAL_APPROVED,
                'approved_by_admin_id' => $admin->id,
                'approved_at' => $now->copy()->subDays(3),
                'admin_notes' => 'معتمد وجاهز للطلاب.',
                'is_active' => true,
            ]
        );
        $resPhysics->assignments()->syncWithoutDetaching([
            $physicsAssignment->id => ['attached_by_teacher_id' => $teacherPhysics->id],
        ]);

        // 15. Live Sessions & Apology Demo
        // Free Intro Session (Anyone can join)
        $freeSession = LiveSession::updateOrCreate(
            [
                'teacher_id' => $teacherMath->id,
                'title' => 'حصة افتتاحية مجانية: كيف تتفوق في رياضيات الثانوية',
            ],
            [
                'description' => 'جلسة تعريفية عامة ومجانية ومفتوحة لجميع الطلاب للتعرف على طريقة تدريس المنهج.',
                'scheduled_at' => Carbon::now('Asia/Qatar')->addDays(1)->setTime(18, 0, 0)->utc(),
                'status' => LiveSession::STATUS_SCHEDULED,
                'is_free' => true,
            ]
        );

        // Group Live Session
        $groupSession = LiveSession::updateOrCreate(
            [
                'teacher_id' => $teacherMath->id,
                'teaching_group_id' => $mathGroup->id,
                'title' => 'البث المباشر التفاعلي: حل مسائل الجبر المتقدمة',
            ],
            [
                'description' => 'حصة بث مباشر تفاعلية مع الطلاب لحل الأسئلة المباشرة وتطبيق النظريات.',
                'scheduled_at' => Carbon::now('Asia/Qatar')->addDays(2)->setTime(19, 0, 0)->utc(),
                'status' => LiveSession::STATUS_SCHEDULED,
                'is_free' => false,
            ]
        );

        LiveSessionAttendee::firstOrCreate([
            'live_session_id' => $groupSession->id,
            'user_id' => $student1->id,
        ]);

        // Past session with Teacher Apology
        $apologySession = LiveSession::updateOrCreate(
            [
                'teacher_id' => $teacherMath->id,
                'teaching_group_id' => $mathGroup->id,
                'title' => 'حصة مراجعة المتطابقات الجبرية (معتذر عنها)',
            ],
            [
                'description' => 'حصة تم الاعتذار عنها وتحديد موعد تعويضي بديل للطلاب.',
                'scheduled_at' => $now->copy()->subDays(3),
                'status' => 'cancelled',
                'is_free' => false,
            ]
        );

        LiveSessionApology::updateOrCreate(
            ['live_session_id' => $apologySession->id],
            [
                'teacher_id' => $teacherMath->id,
                'reason' => 'عطل تقني طارئ في شبكة الاتصال، وتم جدولة حصة تعويضية.',
                'status' => 'approved',
                'created_at' => $now->copy()->subDays(3),
            ]
        );

        // 16. Private Booking Slot
        PrivateSessionSlot::updateOrCreate(
            [
                'teaching_assignment_id' => $mathAssignment->id,
                'starts_at' => Carbon::now('Asia/Qatar')->addDays(3)->setTime(20, 0, 0)->utc(),
            ],
            [
                'ends_at' => Carbon::now('Asia/Qatar')->addDays(3)->setTime(21, 0, 0)->utc(),
                'timezone' => 'Asia/Qatar',
                'status' => 'available',
                'is_free_intro' => true,
            ]
        );

        // 17. Flush caches
        foreach (['platform_settings', 'home.grades', 'home.featured_teachers', 'admin_platform_stats'] as $key) {
            Cache::forget($key);
        }

        $this->command?->info('✅ تم تجهيز كافة البيانات التجريبية الشاملة للمنصة بنجاح!');
    }
}
