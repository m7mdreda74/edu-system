<?php

declare(strict_types=1);

use App\Application\Scheduling\Services\SessionBookingService;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Communication\Notifications\AdminLiveSessionStatusNotification;
use App\Domain\Communication\Notifications\AdminSessionBookingNotification;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// Binds $this inside every Pest closure in this file to Tests\TestCase.
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->teacher = User::factory()->create(['email_verified_at' => now()]);
    $this->teacher->assignRole('teacher');

    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->student->assignRole('student');

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => Subject::query()->firstOrFail()->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);

    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
    ]);
});

it('notifies admins when a teacher starts and ends a live class', function () {
    $session = LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'حصة مراجعة',
        'scheduled_at' => now()->addHour(),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    Notification::fake();

    $this->actingAs($this->teacher)
        ->patch(route('teacher.live-sessions.status', $session->id), ['status' => LiveSession::STATUS_LIVE])
        ->assertRedirect();

    Notification::assertSentTo($this->admin, AdminLiveSessionStatusNotification::class, function ($notification): bool {
        return $notification->status === LiveSession::STATUS_LIVE;
    });

    $this->actingAs($this->teacher)
        ->patch(route('teacher.live-sessions.status', $session->id), ['status' => LiveSession::STATUS_ENDED])
        ->assertRedirect();

    Notification::assertSentTo($this->admin, AdminLiveSessionStatusNotification::class, function ($notification): bool {
        return $notification->status === LiveSession::STATUS_ENDED;
    });
});

it('notifies admins with the student and booking details', function () {
    Notification::fake();

    app(SessionBookingService::class)->bookGroup($this->student, $this->group->id);

    Notification::assertSentTo($this->admin, AdminSessionBookingNotification::class, function ($notification): bool {
        $message = $notification->toArray($this->admin)['message'];

        return str_contains($message, $this->student->name)
            && str_contains($message, $this->teacher->name)
            && str_contains($message, $this->group->name);
    });
});
