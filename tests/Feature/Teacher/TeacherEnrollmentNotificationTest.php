<?php

declare(strict_types=1);

use App\Application\Subscription\Services\SubscriptionService;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Communication\Notifications\StudentSubscribedNotification;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\TeachingGroupSchedule;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->teacher = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
    $this->teacher->assignRole('teacher');

    $this->student = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
    $this->student->assignRole('student');

    $this->subject = Subject::factory()->create(['name' => 'الرياضيات']);
    $this->grade = GradeLevel::factory()->create(['name' => 'الصف العاشر', 'key' => 'grade-10']);

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => $this->subject->id,
        'grade_level_id' => $this->grade->id,
        'accepts_private' => true,
        'private_monthly_price' => 50000,
        'is_active' => true,
    ]);

    $this->subService = app(SubscriptionService::class);
});

it('notifies teacher with student name and exact 2-day schedule when a private group is booked and activated', function () {
    Notification::fake();

    // Create 2-day weekly private group (Saturday & Tuesday at 16:00)
    $privateGroup = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'name' => 'موعد خاص أسبوعي',
        'capacity' => 1,
        'is_private' => true,
        'monthly_price' => 50000,
        'currency' => 'QAR',
        'is_active' => true,
    ]);

    TeachingGroupSchedule::create([
        'teaching_group_id' => $privateGroup->id,
        'day_of_week' => 6, // Saturday
        'start_time' => '16:00',
        'end_time' => '17:00',
        'duration_minutes' => 60,
    ]);

    TeachingGroupSchedule::create([
        'teaching_group_id' => $privateGroup->id,
        'day_of_week' => 2, // Tuesday
        'start_time' => '16:00',
        'end_time' => '17:00',
        'duration_minutes' => 60,
    ]);

    $subscription = $this->subService->openForGroup($this->student, $privateGroup);
    $this->subService->activate($subscription);

    Notification::assertSentTo(
        $this->teacher,
        StudentSubscribedNotification::class,
        function (StudentSubscribedNotification $notification) {
            $data = $notification->toArray($this->teacher);
            expect($data['type'])->toBe('private');
            expect($data['title'])->toContain('برايفت');
            expect($data['message'])->toContain($this->student->name);
            expect($data['message'])->toContain('السبت');
            expect($data['message'])->toContain('الثلاثاء');
            expect($data['message'])->toContain('مساءً');
            return true;
        }
    );
});

it('notifies teacher with student name, group name, and schedule when a regular group is activated', function () {
    Notification::fake();

    $group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'name' => 'مجموعة الأوائل',
        'capacity' => 15,
        'is_private' => false,
        'monthly_price' => 30000,
        'currency' => 'QAR',
        'is_active' => true,
    ]);

    TeachingGroupSchedule::create([
        'teaching_group_id' => $group->id,
        'day_of_week' => 0, // Sunday
        'start_time' => '18:00',
        'end_time' => '19:30',
        'duration_minutes' => 90,
    ]);

    TeachingGroupSchedule::create([
        'teaching_group_id' => $group->id,
        'day_of_week' => 3, // Wednesday
        'start_time' => '18:00',
        'end_time' => '19:30',
        'duration_minutes' => 90,
    ]);

    $subscription = $this->subService->openForGroup($this->student, $group);
    $this->subService->activate($subscription);

    Notification::assertSentTo(
        $this->teacher,
        StudentSubscribedNotification::class,
        function (StudentSubscribedNotification $notification) use ($group) {
            $data = $notification->toArray($this->teacher);
            expect($data['type'])->toBe('group');
            expect($data['title'])->toContain('المجموعة');
            expect($data['message'])->toContain($this->student->name);
            expect($data['message'])->toContain($group->name);
            expect($data['message'])->toContain('الأحد');
            expect($data['message'])->toContain('الأربعاء');
            return true;
        }
    );
});

it('displays the student enrollment and schedule in the teacher dashboard', function () {
    $privateGroup = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'name' => 'موعد خاص أسبوعي',
        'capacity' => 1,
        'is_private' => true,
        'monthly_price' => 50000,
        'currency' => 'QAR',
        'is_active' => true,
    ]);

    TeachingGroupSchedule::create([
        'teaching_group_id' => $privateGroup->id,
        'day_of_week' => 6, // Saturday
        'start_time' => '16:00',
        'end_time' => '17:00',
        'duration_minutes' => 60,
    ]);

    $subscription = $this->subService->openForGroup($this->student, $privateGroup);
    $this->subService->activate($subscription);

    $response = $this->actingAs($this->teacher)->get(route('teacher.dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('recent_enrollments', 1)
            ->where('recent_enrollments.0.student_name', $this->student->name)
            ->where('recent_enrollments.0.is_private', true)
            ->where('recent_enrollments.0.subject_name', $this->subject->name)
            ->has('assignments.0.groups', 1)
            ->where('assignments.0.groups.0.is_private', true)
            ->where('assignments.0.groups.0.students.0.name', $this->student->name)
        );
});
