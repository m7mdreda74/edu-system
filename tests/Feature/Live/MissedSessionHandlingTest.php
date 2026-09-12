<?php

declare(strict_types=1);

use App\Application\Learning\Services\MissedLiveSessionService;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionApology;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->teacher = User::factory()->create(['email_verified_at' => now()]);
    $this->teacher->assignRole('teacher');

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => Subject::query()->firstOrFail()->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);

    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'capacity' => 15,
        'monthly_price' => 40_000,
    ]);

    $this->student = User::factory()->create();
    $this->student->assignRole('student');

    SessionBooking::create([
        'student_id' => $this->student->id,
        'teaching_group_id' => $this->group->id,
        'status' => 'confirmed',
    ]);
});

it('automatically cancels an overdue unstarted session and logs an unexcused absence apology', function () {
    $overdueSession = LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'حصة قديمة لم تبدأ',
        'scheduled_at' => now()->subHours(3),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    $cancelled = app(MissedLiveSessionService::class)->cancelOverdueSessions();

    expect($cancelled)->toBe(1);

    $overdueSession->refresh();
    expect($overdueSession->status)->toBe(LiveSession::STATUS_CANCELLED)
        ->and($overdueSession->apology)->not->toBeNull()
        ->and($overdueSession->apology->status)->toBe(LiveSessionApology::STATUS_PENDING)
        ->and($overdueSession->apology->reason)->toContain('غياب بدون عذر');
});

it('blocks teacher and student from entering the room for an overdue session and cancels it', function () {
    $overdueSession = LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'حصة متأخرة تجاوزت وقتها',
        'scheduled_at' => now()->subHours(4),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    $this->actingAs($this->teacher)
        ->get(route('live-sessions.room', $overdueSession->id))
        ->assertForbidden();

    expect($overdueSession->fresh()->status)->toBe(LiveSession::STATUS_CANCELLED);

    $this->actingAs($this->student)
        ->get(route('live-sessions.room', $overdueSession->id))
        ->assertForbidden();
});

it('lets teacher resolve the unexcused absence apology with a makeup class without deduction', function () {
    $overdueSession = LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'حصة متأخرة تحتاج تعويض',
        'scheduled_at' => now()->subHours(5),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    app(MissedLiveSessionService::class)->cancelOverdueSessions();
    $apology = $overdueSession->fresh()->apology;

    $makeupTime = now()->addDays(2)->setSecond(0);

    $this->actingAs($this->teacher)
        ->post(route('teacher.session-apologies.makeup', $apology->id), [
            'scheduled_at' => $makeupTime->toDateTimeString(),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $apology->refresh();
    expect($apology->status)->toBe(LiveSessionApology::STATUS_MAKEUP_SCHEDULED)
        ->and($apology->deduction_amount)->toBe(0)
        ->and($apology->makeupSession)->not->toBeNull();
});

it('allows admin to deduct from teacher if makeup session is not scheduled', function () {
    $overdueSession = LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'حصة متأخرة لم تعوض',
        'scheduled_at' => now()->subHours(5),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    app(MissedLiveSessionService::class)->cancelOverdueSessions();
    $apology = $overdueSession->fresh()->apology;

    $this->actingAs($this->admin)
        ->post(route('admin.session-apologies.deduct', $apology->id), [
            'amount_qar' => 100,
            'admin_note' => 'خصم غياب حصة لعدم التعويض',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $apology->refresh();
    expect($apology->status)->toBe(LiveSessionApology::STATUS_DEDUCTED)
        ->and($apology->deduction_amount)->toBe(10_000);
});

it('provides the enrolled students for the teaching group in schedule', function () {
    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.teaching-schedule'));

    $response->assertOk();

    $assignments = $response->viewData('page')['props']['assignments'];
    $group = $assignments[0]['groups'][0];

    expect($group['students'])->toBeArray()
        ->and(count($group['students']))->toBe(1)
        ->and($group['students'][0]['id'])->toBe($this->student->id)
        ->and($group['students'][0]['name'])->toBe($this->student->name);
});

it('runs sessions:cancel-overdue console command cleanly', function () {
    LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'حصة أمر الكونسول',
        'scheduled_at' => now()->subHours(6),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    $this->artisan('sessions:cancel-overdue')
        ->expectsOutputToContain('Cancelled 1 overdue unstarted live session(s)')
        ->assertExitCode(0);
});
