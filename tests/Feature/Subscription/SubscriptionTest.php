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
use App\Policies\MaterialPolicy;
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

    $grade = GradeLevel::where('key', 'grade_12_science')->firstOrFail();
    $subject = Subject::factory()->create();

    $teacher = User::factory()->create(['is_active' => true]);
    $teacher->assignRole('teacher');

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'grade_level_id' => $grade->id,
        'private_monthly_price' => 90_000,
        'accepts_private' => true,
    ]);

    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'monthly_price' => 45_000,
        'capacity' => 2,
    ]);

    // The syllabus hangs off the assignment, so material needs a unit to sit in.
    $this->unit = CurriculumUnit::create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => AcademicTerm::currentOrNext()->id,
        'order' => 1,
        'title' => 'الوحدة الأولى',
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

it('rechecks the last group seat when pending payments are activated', function () {
    $this->group->update(['capacity' => 1]);
    $otherStudent = User::factory()->create();
    $otherStudent->assignRole('student');

    $first = $this->service->openForGroup($this->student, $this->group);
    $second = $this->service->openForGroup($otherStudent, $this->group);

    $this->service->activate($first);

    expect(fn () => $this->service->activate($second))->toThrow(LogicException::class)
        ->and($second->fresh()->status)->toBe(Subscription::STATUS_PENDING)
        ->and($this->group->fresh()->activeBookings()->count())->toBe(1);
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
        'title' => 'الحصة الأولى',
        'video_url' => 'https://example.com/video.mp4',
        'duration_seconds' => 600,
        'order' => 1,
        'is_free_preview' => false,
    ]);

    $policy = new MaterialPolicy;

    expect($policy->watch($this->student, $material))->toBeFalse();

    $this->service->activate($this->service->openForGroup($this->student, $this->group));

    expect($policy->watch($this->student->fresh(), $material))->toBeTrue();
});

it('lets anyone watch a free preview', function () {
    $preview = GroupMaterial::create([
        'curriculum_unit_id' => $this->unit->id,
        'title' => 'معاينة',
        'duration_seconds' => 300,
        'order' => 1,
        'is_free_preview' => true,
    ]);

    expect((new MaterialPolicy)->watch($this->student, $preview))->toBeTrue();
});

it('sends the student to checkout when they subscribe from the profile', function () {
    $this->actingAs($this->student)
        ->post(route('student.subscribe.group', ['groupId' => $this->group->id]))
        ->assertRedirect();

    $subscription = Subscription::where('student_id', $this->student->id)->firstOrFail();

    expect($subscription->status)->toBe(Subscription::STATUS_PENDING);
});

it('sends the student to checkout for a private subscription priced by the admin', function () {
    $this->actingAs($this->student)
        ->post(route('student.subscribe.private', ['assignmentId' => $this->assignment->id]))
        ->assertRedirect();

    $subscription = Subscription::where('student_id', $this->student->id)->firstOrFail();

    expect($subscription->type)->toBe(Subscription::TYPE_PRIVATE)
        ->and($subscription->monthly_price)->toBe(90_000)
        ->and($subscription->status)->toBe(Subscription::STATUS_PENDING);
});

it('creates one private lesson request and opens the agreement chat with the teacher', function () {
    Notification::fake();

    $response = $this->actingAs($this->student)
        ->post(route('student.private-lesson-requests.store', ['assignmentId' => $this->assignment->id]));

    $privateRequest = PrivateLessonRequest::firstOrFail();

    $response->assertRedirect(route('chat.index', ['conversation' => $privateRequest->conversation_id]));

    expect($privateRequest->student_id)->toBe($this->student->id)
        ->and($privateRequest->teaching_assignment_id)->toBe($this->assignment->id)
        ->and($privateRequest->status)->toBe(PrivateLessonRequest::STATUS_PENDING)
        ->and(ChatMessage::where('conversation_id', $privateRequest->conversation_id)
            ->where('sender_id', $this->student->id)
            ->where('message', 'like', '%المواعيد المنشورة%')
            ->exists())->toBeTrue()
        ->and(ChatMessage::where('conversation_id', $privateRequest->conversation_id)
            ->where('message', 'like', '%الأوقات أو الملاحظات%')
            ->exists())->toBeFalse()
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

it('publishes private slots for one student and closes the slot after the first booking', function () {
    $teacher = $this->assignment->teacher;
    $startsAt = now()->addDays(4)->setTime(18, 0);

    $this->actingAs($teacher)
        ->post(route('teacher.private-slots.store'), [
            'teaching_assignment_id' => $this->assignment->id,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $startsAt->copy()->addHour()->toDateTimeString(),
            'timezone' => 'Asia/Qatar',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $slot = PrivateSessionSlot::where('is_free_intro', false)->firstOrFail();

    $this->actingAs($this->student)
        ->get(route('teachers.show', $teacher->id))
        ->assertInertia(fn ($page) => $page
            ->where('assignments.0.has_private_subscription', false)
            ->where('assignments.0.private_slots.0.id', $slot->id));

    $this->service->activate($this->service->openForPrivate($this->student, $this->assignment));

    $this->actingAs($this->student)
        ->get(route('teachers.show', $teacher->id))
        ->assertInertia(fn ($page) => $page
            ->where('assignments.0.has_private_subscription', true)
            ->where('assignments.0.private_slots.0.id', $slot->id));

    $this->actingAs($this->student)
        ->post(route('student.private-slots.book', $slot->id))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($slot->fresh()->status)->toBe('booked')
        ->and(SessionBooking::where('private_session_slot_id', $slot->id)
            ->where('status', 'confirmed')->count())->toBe(1);

    $this->actingAs($this->student)
        ->get(route('teachers.show', $teacher->id))
        ->assertInertia(fn ($page) => $page->has('assignments.0.private_slots', 0));

    $otherStudent = User::factory()->create(['email_verified_at' => now()]);
    $otherStudent->assignRole('student');
    $this->service->activate($this->service->openForPrivate($otherStudent, $this->assignment));

    $this->actingAs($otherStudent)
        ->post(route('student.private-slots.book', $slot->id))
        ->assertSessionHas('error');

    expect(SessionBooking::where('private_session_slot_id', $slot->id)
        ->where('status', 'confirmed')->count())->toBe(1);
});

it('reopens a booked private slot when the private subscription is cancelled or expires', function () {
    $slot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $this->assignment->id,
        'starts_at' => now()->addDays(5)->setTime(18, 0),
        'ends_at' => now()->addDays(5)->setTime(19, 0),
        'timezone' => 'Asia/Qatar',
        'is_free_intro' => false,
        'status' => 'available',
    ]);

    $subscription = $this->service->activate($this->service->openForPrivate($this->student, $this->assignment));

    $this->actingAs($this->student)
        ->post(route('student.private-slots.book', $slot->id))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->service->cancel($subscription->fresh());

    expect($slot->fresh()->status)->toBe('available')
        ->and(SessionBooking::where('private_session_slot_id', $slot->id)->where('status', 'confirmed')->exists())->toBeFalse();

    $nextStudent = User::factory()->create(['email_verified_at' => now()]);
    $nextStudent->assignRole('student');
    $nextSubscription = $this->service->activate($this->service->openForPrivate($nextStudent, $this->assignment));
    $this->actingAs($nextStudent)
        ->post(route('student.private-slots.book', $slot->id))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $nextSubscription->fresh()->update(['period_end' => now()->subDay()]);
    expect($this->service->expireLapsed())->toBe(1)
        ->and($slot->fresh()->status)->toBe('available')
        ->and(SessionBooking::where('private_session_slot_id', $slot->id)->where('status', 'confirmed')->exists())->toBeFalse();
});

it('hides a full group and shows it again after its seat is released', function () {
    $this->group->update(['capacity' => 1]);
    $occupant = User::factory()->create();
    $occupant->assignRole('student');
    $subscription = $this->service->activate($this->service->openForGroup($occupant, $this->group));

    $this->get(route('teachers.show', $this->assignment->teacher_id))
        ->assertInertia(fn ($page) => $page->has('assignments.0.groups', 0));

    $this->service->cancel($subscription);

    $this->get(route('teachers.show', $this->assignment->teacher_id))
        ->assertInertia(fn ($page) => $page
            ->has('assignments.0.groups', 1)
            ->where('assignments.0.groups.0.id', $this->group->id));
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

it('does not report a subscription as active after its paid day ends', function () {
    $subscription = $this->service->activate($this->service->openForGroup($this->student, $this->group));
    $subscription->update(['period_end' => today()]);

    expect($subscription->fresh()->isActive())->toBeTrue()
        ->and($subscription->fresh()->effectiveStatus())->toBe(Subscription::STATUS_ACTIVE);

    $subscription->update(['period_end' => today()->subDay()]);

    expect($subscription->fresh()->isActive())->toBeFalse()
        ->and($subscription->fresh()->effectiveStatus())->toBe(Subscription::STATUS_EXPIRED);
});
