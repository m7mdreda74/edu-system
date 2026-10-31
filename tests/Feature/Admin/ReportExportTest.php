<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('teacher', 'web');
    Role::findOrCreate('student', 'web');

    $this->admin = User::factory()->create(['name' => 'الأدمن الرئيسي']);
    $this->admin->assignRole('admin');

    $this->teacher = User::factory()->create([
        'name' => 'الأستاذ أحمد فؤاد',
        'email' => 'ahmed.teacher@example.com',
        'phone' => '+97455112233',
    ]);
    $this->teacher->assignRole('teacher');

    $this->student = User::factory()->create([
        'name' => 'الطالب عمر محمد',
        'email' => 'omar.student@example.com',
        'phone' => '+97466112233',
        'grade_level' => 'secondary-1',
    ]);
    $this->student->assignRole('student');

    $subject = Subject::query()->firstOrCreate(['name' => 'الفيزياء', 'slug' => 'physics']);
    $gradeLevel = GradeLevel::query()->firstOrCreate([
        'key' => 'secondary-1',
    ], [
        'name' => 'الصف الأول الثانوي',
        'stage' => 'secondary',
        'order' => 10,
    ]);

    $this->teacher->update(['subject_id' => $subject->id]);

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => $subject->id,
        'grade_level_id' => $gradeLevel->id,
    ]);

    $this->group = TeachingGroup::factory()->create([
        'name' => 'مجموعة العباقرة للفيزياء',
        'teaching_assignment_id' => $this->assignment->id,
    ]);

    $this->subscription = Subscription::factory()->active()->create([
        'student_id' => $this->student->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $this->group->id,
    ]);

    $this->session = LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'حصة قوانين الحركة والسرعة',
        'scheduled_at' => now()->subDay(),
        'started_at' => now()->subDay(),
        'ended_at' => now()->subDay()->addHour(),
        'status' => LiveSession::STATUS_ENDED,
    ]);

    $this->attendee = LiveSessionAttendee::create([
        'live_session_id' => $this->session->id,
        'user_id' => $this->student->id,
        'joined_at' => now()->subDay(),
        'left_at' => now()->subDay()->addMinutes(50),
    ]);
});

it('renders admin reports with comprehensive students dataset and metrics', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.reports', ['report_type' => 'students']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Reports')
            ->where('filters.report_type', 'students')
            ->has('students')
            ->has('teachers')
            ->where('summary.students_registered', fn ($val) => $val >= 1)
            ->where('summary.active_subscriptions', fn ($val) => $val >= 1)
        );
});

it('exports formatted excel spreadsheet with all sheets, RTL, and UTF-8 encoding', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.reports.export.excel', ['report_type' => 'all']))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/vnd.ms-excel')
        ->and($response->headers->get('content-disposition'))->toContain('attachment; filename="altafawwuq-report-all-');

    $content = $response->streamedContent();

    expect($content)->toContain('<?xml version="1.0" encoding="UTF-8"?>')
        ->and($content)->toContain('progid="Excel.Sheet"')
        ->and($content)->toContain('ss:RightToLeft="1"')
        ->and($content)->toContain('المعلمون')
        ->and($content)->toContain('الطلاب')
        ->and($content)->toContain('المجموعات والدورات')
        ->and($content)->toContain('الحصص المباشرة')
        ->and($content)->toContain('سجل الحضور والغياب')
        ->and($content)->toContain('الأستاذ أحمد فؤاد')
        ->and($content)->toContain('الطالب عمر محمد');
});

it('exports formatted excel spreadsheet for students category specifically', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.reports.export.excel', ['report_type' => 'students']))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/vnd.ms-excel')
        ->and($response->headers->get('content-disposition'))->toContain('attachment; filename="altafawwuq-report-students-');

    $content = $response->streamedContent();

    expect($content)->toContain('الطلاب')
        ->and($content)->toContain('الطالب عمر محمد')
        ->and($content)->toContain('omar.student@example.com')
        ->and($content)->toContain('+97466112233');
});

it('renders dedicated standalone pdf view with A4 landscape layout and full metrics', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.reports.export.pdf', ['report_type' => 'all']))
        ->assertOk()
        ->assertViewIs('reports.pdf')
        ->assertViewHasAll(['teachers', 'students', 'groups', 'sessions', 'attendance', 'summary', 'filters']);

    $html = $response->getContent();

    expect($html)->toContain('منصة التفوق التعليمية')
        ->and($html)->toContain('A4 landscape')
        ->and($html)->toContain('تقرير ملخص المدرسين والتكليفات الأكاديمية')
        ->and($html)->toContain('تقرير بيانات ومتابعة الطلاب الشامل')
        ->and($html)->toContain('الأستاذ أحمد فؤاد')
        ->and($html)->toContain('الطالب عمر محمد')
        ->and($html)->toContain('ساعات الحضور');
});

it('prevents non-admins from downloading excel and pdf reports', function (): void {
    $this->actingAs($this->student)
        ->get(route('admin.reports.export.excel'))
        ->assertForbidden();

    $this->actingAs($this->student)
        ->get(route('admin.reports.export.pdf'))
        ->assertForbidden();

    $this->actingAs($this->teacher)
        ->get(route('admin.reports.export.excel'))
        ->assertForbidden();

    $this->actingAs($this->teacher)
        ->get(route('admin.reports.export.pdf'))
        ->assertForbidden();
});
