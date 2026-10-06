<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\TeachingGroupLesson;
use App\Domain\Scheduling\Models\TeachingGroupSchedule;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TestAccountsSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = 'password';

    public function run(): void
    {
        // 1. Ensure Roles
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        $passwordHash = Hash::make(self::DEFAULT_PASSWORD);
        $now = now();

        // 2. Ensure Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@almagd.com'],
            [
                'name' => 'مدير المنصة',
                'phone' => '+97455000001',
                'password' => $passwordHash,
                'email_verified_at' => $now,
                'is_approved' => true,
            ]
        );
        $admin->syncRoles(['admin']);

        // 3. Ensure Subject & Grade
        $subject = Subject::firstOrCreate(
            ['name' => 'الرياضيات'],
            ['code' => 'MATH', 'order' => 1]
        );

        $grade = GradeLevel::where('name', 'الصف العاشر')
            ->orWhere('name', 'الصف السابع الإعدادي')
            ->first() ?? GradeLevel::firstOrCreate(
                ['name' => 'الصف الأول الإعدادي'],
                ['key' => 'prep_1', 'order' => 1, 'is_active' => true]
            );

        $term = AcademicTerm::currentOrNext() ?? AcademicTerm::firstOrCreate(
            ['name' => 'الفصل الدراسي الأول 2026-2027'],
            [
                'starts_at' => $now->copy()->startOfYear(),
                'ends_at' => $now->copy()->endOfYear(),
                'is_current' => true,
            ]
        );

        // 4. Create / Update Test Teacher
        $teacher = User::updateOrCreate(
            ['email' => 'teacher@almagd.com'],
            [
                'name' => 'أ. أحمد منصور (أستاذ الرياضيات)',
                'phone' => '+97455000003',
                'password' => $passwordHash,
                'email_verified_at' => $now,
                'is_approved' => true,
                'is_active' => true,
                'is_featured' => true,
                'headline' => 'خبير تدريس الرياضيات والقدرات والمناهج التعليمية',
                'bio' => 'معلم رياضيات معتمد بخبرة تزيد عن 10 سنوات في تبسيط المفاهيم الرياضية وإعداد الطلاب للمتفوقين والاختبارات المتقدمة.',
                'years_experience' => 10,
                'subject_id' => $subject->id,
            ]
        );
        $teacher->syncRoles(['teacher']);

        // 5. Teaching Assignment
        $assignment = TeachingAssignment::updateOrCreate(
            [
                'teacher_id' => $teacher->id,
                'subject_id' => $subject->id,
                'grade_level_id' => $grade->id,
            ],
            [
                'monthly_group_rate' => 200,
                'private_session_rate' => 120,
                'accepts_private' => true,
                'is_active' => true,
            ]
        );

        // 6. Teaching Group
        $group = TeachingGroup::updateOrCreate(
            [
                'teaching_assignment_id' => $assignment->id,
                'name' => 'مجموعة العباقرة — الرياضيات (السبت والثلاثاء)',
            ],
            [
                'academic_term_id' => $term->id,
                'capacity' => 25,
                'monthly_price' => 200,
                'currency' => 'QAR',
                'day_of_week' => 6, // Saturday
                'start_time' => '19:00',
                'end_time' => '20:00',
                'duration_minutes' => 60,
                'timezone' => 'Asia/Qatar',
                'is_active' => true,
            ]
        );

        // 7. Group Schedules (Saturday 7 PM & Tuesday 7 PM)
        TeachingGroupSchedule::updateOrCreate(
            [
                'teaching_group_id' => $group->id,
                'day_of_week' => 6,
            ],
            [
                'start_time' => '19:00',
                'end_time' => '20:00',
                'duration_minutes' => 60,
            ]
        );

        TeachingGroupSchedule::updateOrCreate(
            [
                'teaching_group_id' => $group->id,
                'day_of_week' => 2,
            ],
            [
                'start_time' => '19:00',
                'end_time' => '20:00',
                'duration_minutes' => 60,
            ]
        );

        // 8. Curriculum Unit & Published Lessons
        $unit = CurriculumUnit::updateOrCreate(
            [
                'teaching_assignment_id' => $assignment->id,
                'title' => 'الوحدة الأولى: الجبر والمعادلات الرياضية',
            ],
            [
                'academic_term_id' => $term->id,
                'order' => 1,
                'is_published' => true,
                'description' => 'شرح تأسيسي متكامل لمفاهيم الجبر وحل المعادلات الخطية والتطبيقية.',
            ]
        );

        GroupMaterial::updateOrCreate(
            [
                'curriculum_unit_id' => $unit->id,
                'title' => 'الدرس الأول: مقدمة في المعادلات الخطية وتطبيقاتها',
            ],
            [
                'academic_term_id' => $term->id,
                'order' => 1,
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'description' => 'شرح تفاعلي لأساسيات حل المعادلات بمجهول واحد وطرق التبسيط السريعة.',
                'is_free_preview' => true,
                'duration_seconds' => 1800,
            ]
        );

        GroupMaterial::updateOrCreate(
            [
                'curriculum_unit_id' => $unit->id,
                'title' => 'الدرس الثاني: حل المعادلات والمسائل الحسابية المتقدمة',
            ],
            [
                'academic_term_id' => $term->id,
                'order' => 2,
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'description' => 'تمارين وتطبيقات عملية على حل المسائل المركبة والمعادلات.',
                'is_free_preview' => false,
                'duration_seconds' => 2400,
            ]
        );

        // 9. Group Lessons & Scheduled Live Session
        $sessionTime = Carbon::now('Asia/Qatar')->addDays(1)->setTime(19, 0, 0)->utc();

        $liveSession = LiveSession::updateOrCreate(
            [
                'teacher_id' => $teacher->id,
                'teaching_group_id' => $group->id,
                'title' => 'حصة البث المباشر: شرح الجبر التفاعلي',
            ],
            [
                'description' => 'حصة تدريسية مباشرة مع المدرس لحل المسائل وتلقي أسئلة الطلاب مباشرة.',
                'scheduled_at' => $sessionTime,
                'status' => LiveSession::STATUS_SCHEDULED,
            ]
        );

        TeachingGroupLesson::updateOrCreate(
            [
                'teaching_group_id' => $group->id,
                'title' => 'الحصة الأولى: تأسيس الجبر وحل المعادلات',
            ],
            [
                'position' => 1,
                'status' => 'scheduled',
                'live_session_id' => $liveSession->id,
            ]
        );

        TeachingGroupLesson::updateOrCreate(
            [
                'teaching_group_id' => $group->id,
                'title' => 'الحصة الثانية: تدريبات وتطبيقات عملية',
            ],
            [
                'position' => 2,
                'status' => 'pending',
            ]
        );

        // 10. Private Slot / Free Intro for the Teacher
        PrivateSessionSlot::updateOrCreate(
            [
                'teaching_assignment_id' => $assignment->id,
                'starts_at' => Carbon::now('Asia/Qatar')->addDays(2)->setTime(20, 30, 0)->utc(),
            ],
            [
                'ends_at' => Carbon::now('Asia/Qatar')->addDays(2)->setTime(21, 30, 0)->utc(),
                'timezone' => 'Asia/Qatar',
                'status' => 'available',
                'is_free_intro' => true,
            ]
        );

        // 11. Create / Update Test Student
        $student = User::updateOrCreate(
            ['email' => 'student@almagd.com'],
            [
                'name' => 'عمر خالد (طالب تجريبي)',
                'phone' => '+97455000004',
                'password' => $passwordHash,
                'email_verified_at' => $now,
                'is_approved' => true,
                'is_active' => true,
                'grade_level' => $grade->name,
            ]
        );
        $student->syncRoles(['student']);

        // 12. Enroll Student in Teacher's Group & Subscription
        Subscription::updateOrCreate(
            [
                'student_id' => $student->id,
                'teaching_group_id' => $group->id,
            ],
            [
                'type' => Subscription::TYPE_GROUP,
                'teaching_assignment_id' => $assignment->id,
                'monthly_price' => 200,
                'currency' => 'QAR',
                'period_start' => $now->copy()->startOfDay(),
                'period_end' => $now->copy()->addMonth()->endOfDay(),
                'status' => Subscription::STATUS_ACTIVE,
                'auto_renew' => true,
            ]
        );

        SessionBooking::updateOrCreate(
            [
                'student_id' => $student->id,
                'teaching_group_id' => $group->id,
            ],
            [
                'status' => 'confirmed',
                'booked_at' => $now,
            ]
        );

        LiveSessionAttendee::firstOrCreate([
            'live_session_id' => $liveSession->id,
            'user_id' => $student->id,
        ]);
    }
}
