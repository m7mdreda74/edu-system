<?php

declare(strict_types=1);

use App\Application\Subscription\Services\SubscriptionService;
use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Communication\Models\ChatMessage;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Scheduling\Models\PrivateLessonRequest;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// Binds $this inside every Pest closure in this file to Tests\TestCase.
uses(TestCase::class, RefreshDatabase::class);

// ─── Monthly subscriptions replace one-off course purchases ──────────────────

beforeEach(function () {
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $grade   = GradeLevel::where('key', 'grade_12_science')->firstOrFail();
    $subject = Subject::factory()->create();

    $teacher = User::factory()->create(['is_active' => true]);
    $teacher->assignRole('teacher');

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id'            => $teacher->id,
        'subject_id'            => $subject->id,
        'grade_level_id'        => $grade->id,
        'private_monthly_price' => 90_000,
        'accepts_private'       => true,
    ]);

    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'monthly_price'          => 45_000,
        'capacity'               => 2,
    ]);

    // The syllabus hangs off the assignment, so material needs a unit to sit in.
    $this->unit = CurriculumUnit::create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id'       => AcademicTerm::currentOrNext()->id,
        'order'                  => 1,
        'title'                  => 'الوحدة الأولى',
    ]);

    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->student->assignRole('student');

    $this->service = app(SubscriptionService::class);
});

it('opens a pending subscription priced from the group', function () {
    $subscription = $this->service->openForGroup($this->student, $this->group);

    expect($subscription->status)->toBe(Subscription::STATUS_PENDING)
        ->and($subscription->monthly_price)->toBe(45_000)
        ->and($subscription->teaching_assignment_id)->toBe($this->assignment->id)
        ->and($subscription->period_end->toDateString())
        ->toBe(now()->startOfDay()->addMonth()->toDateString());
});

it('reserves the seat only once the subscription is activated', function () {
    $subscription = $this->service->openForGroup($this->student, $this->group);

    expect(SessionBooking::where('student_id', $this->student->id)->exists())->toBeFalse();

    $this->service->activate($subscription);

    expect(SessionBooking::where('student_id', $this->student->id)->where('status', 'confirmed')->exists())->toBeTrue();
});

it('refuses to subscribe twice to the same group', function () {
    $this->service->activate($this->service->openForGroup($this->student, $this->group));

    expect(fn () => $this->service->openForGroup($this->student, $this->group))
        ->toThrow(LogicException::class);
});

it('refuses to subscribe once the group is full', function () {
    foreach (range(1, 2) as $index) {
        $other = User::factory()->create();
        $other->assignRole('student');
        $this->service->activate($this->service->openForGroup($other, $this->group));
    }

    expect(fn () => $this->service->openForGroup($this->student, $this->group))
        ->toThrow(LogicException::class);
});

it('activating twice is idempotent', function () {
    $subscription = $this->service->openForGroup($this->student, $this->group);

    $this->service->activate($subscription);
    $this->service->activate($subscription->fresh());

    expect(SessionBooking::where('student_id', $this->student->id)->count())->toBe(1);
});

it('renews from the current period end so early renewal loses no days', function () {
    $subscription = $this->service->openForGroup($this->student, $this->group);
    $this->service->activate($subscription);

    $renewal = $this->service->renew($subscription->fresh());

    expect($renewal->period_start->toDateString())->toBe($subscription->period_end->toDateString())
        ->and($renewal->status)->toBe(Subscription::STATUS_PENDING);
});

it('cancelling releases the seat', function () {
    $subscription = $this->service->openForGroup($this->student, $this->group);
    $this->service->activate($subscription);

    $this->service->cancel($subscription->fresh());

    expect(SessionBooking::where('student_id', $this->student->id)->where('status', 'confirmed')->exists())->toBeFalse();
});

it('expires lapsed subscriptions and frees their seats', function () {
    $subscription = $this->service->openForGroup($this->student, $this->group);
    $this->service->activate($subscription);
    $subscription->fresh()->update(['period_end' => now()->subDay()]);

    expect($this->service->expireLapsed())->toBe(1)
        ->and($subscription->fresh()->status)->toBe(Subscription::STATUS_EXPIRED);
});

it('locks paid material behind a live subscription', function () {
    $material = GroupMaterial::create([
        'curriculum_unit_id' => $this->unit->id,
        'title'              => 'الحصة الأولى',
        'video_url'          => 'https://example.com/video.mp4',
        'duration_seconds'   => 600,
        'order'              => 1,
        'is_free_preview'    => false,
    ]);

    $policy = new App\Policies\MaterialPolicy();

    expect($policy->watch($this->student, $material))->toBeFalse();

    $this->service->activate($this->service->openForGroup($this->student, $this->group));

    expect($policy->watch($this->student->fresh(), $material))->toBeTrue();
});

it('lets anyone watch a free preview', function () {
    $preview = GroupMaterial::create([
        'curriculum_unit_id' => $this->unit->id,
        'title'              => 'معاينة',
        'duration_seconds'   => 300,
        'order'              => 1,
        'is_free_preview'    => true,
    ]);

    expect((new App\Policies\MaterialPolicy())->watch($this->student, $preview))->toBeTrue();
});

it('sends the student to checkout when they subscribe from the profile', function () {
    $this->actingAs($this->student)
        ->post(route('student.subscribe.group', ['groupId' => $this->group->id]))
        ->assertRedirect();

    $subscription = Subscription::where('student_id', $this->student->id)->firstOrFail();

    expect($subscription->status)->toBe(Subscription::STATUS_PENDING);
});

it('creates one private lesson request and opens the agreement chat with the teacher', function () {
    Notification::fake();

    $response = $this->actingAs($this->student)
        ->post(route('student.private-lesson-requests.store', ['assignmentId' => $this->assignment->id]), [
            'note' => 'الأحد بعد السادسة مناسب لي',
        ]);

    $privateRequest = PrivateLessonRequest::firstOrFail();

    $response->assertRedirect(route('chat.index', ['conversation' => $privateRequest->conversation_id]));

    expect($privateRequest->student_id)->toBe($this->student->id)
        ->and($privateRequest->teaching_assignment_id)->toBe($this->assignment->id)
        ->and($privateRequest->status)->toBe(PrivateLessonRequest::STATUS_PENDING)
        ->and(ChatMessage::where('conversation_id', $privateRequest->conversation_id)
            ->where('sender_id', $this->student->id)
            ->where('message', 'like', '%الأحد بعد السادسة%')
            ->exists())->toBeTrue()
        ->and(Subscription::where('student_id', $this->student->id)->exists())->toBeFalse();

    Notification::assertSentTo($this->assignment->teacher, GenericDatabaseNotification::class);

    $this->actingAs($this->student)
        ->post(route('student.private-lesson-requests.store', ['assignmentId' => $this->assignment->id]))
        ->assertRedirect(route('chat.index', ['conversation' => $privateRequest->conversation_id]));

    expect(PrivateLessonRequest::count())->toBe(1);
});

it('shows the pending private request on the teacher profile', function () {
    $this->actingAs($this->student)
        ->post(route('student.private-lesson-requests.store', ['assignmentId' => $this->assignment->id]));

    $this->actingAs($this->student)
        ->get(route('teachers.show', [
            'id' => $this->assignment->teacher_id,
            'grade' => $this->assignment->gradeLevel->key,
            'subject' => $this->assignment->subject_id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('focus.grade', $this->assignment->gradeLevel->key)
            ->where('focus.subject', $this->assignment->subject_id)
            ->where('assignments.0.private_request.status', PrivateLessonRequest::STATUS_PENDING)
            ->where('assignments.0.private_request.conversation_id', PrivateLessonRequest::first()->conversation_id));
});

it('lets a student reserve one free intro session per teacher without creating a payment or subscription', function () {
    Notification::fake();
    $teacher = $this->assignment->teacher;

    $this->actingAs($teacher)
        ->post(route('teacher.free-intro-sessions.store'), [
            'teaching_assignment_id' => $this->assignment->id,
            'starts_at' => now()->addDays(2)->setTime(16, 0)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(2)->setTime(17, 0)->format('Y-m-d H:i:s'),
            'timezone' => 'Asia/Qatar',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $firstSlot = PrivateSessionSlot::firstOrFail();

    $profileRoute = route('teachers.show', [
        'id' => $teacher->id,
        'grade' => $this->assignment->gradeLevel->key,
        'subject' => $this->assignment->subject_id,
    ]);

    $this->actingAs($this->student)
        ->get($profileRoute)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('assignments.0.free_intro_slots.0.id', $firstSlot->id)
            ->where('freeIntroBooking', null));

    $this->actingAs($this->student)
        ->post(route('student.free-intro-sessions.store', $firstSlot->id))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($firstSlot->fresh())
        ->is_free_intro->toBeTrue()
        ->status->toBe('booked')
        ->and(SessionBooking::where('student_id', $this->student->id)
            ->where('private_session_slot_id', $firstSlot->id)
            ->where('status', 'confirmed')
            ->count())->toBe(1)
        ->and(Subscription::where('student_id', $this->student->id)->count())->toBe(0);

    Notification::assertSentTo($teacher, GenericDatabaseNotification::class);

    $this->actingAs($this->student)
        ->get($profileRoute)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('assignments.0.free_intro_slots', 0)
            ->where('freeIntroBooking.id', SessionBooking::where('student_id', $this->student->id)->value('id')));

    $secondSlot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $this->assignment->id,
        'starts_at' => now()->addDays(3)->setTime(16, 0),
        'ends_at' => now()->addDays(3)->setTime(17, 0),
        'timezone' => 'Asia/Qatar',
        'is_free_intro' => true,
        'status' => 'available',
    ]);

    $this->actingAs($this->student)
        ->post(route('student.free-intro-sessions.store', $secondSlot->id))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($secondSlot->fresh()->status)->toBe('available')
        ->and(SessionBooking::where('student_id', $this->student->id)->count())->toBe(1);
});

it('shows the student their classes', function () {
    $this->service->activate($this->service->openForGroup($this->student, $this->group));

    $this->actingAs($this->student)
        ->get(route('student.my-classes'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Student/MyClasses')
            ->has('subscriptions', 1)
            ->where('subscriptions.0.is_active', true));
});
