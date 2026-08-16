<?php

declare(strict_types=1);

use App\Application\Subscription\Services\SubscriptionService;
use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Communication\Models\Conversation;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\PrivateLessonRequest;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// Binds $this inside every Pest closure in this file to Tests\TestCase.
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('admin');
    $this->teacher = User::factory()->create(['is_active' => true, 'phone' => '52000001']);
    $this->teacher->assignRole('teacher');
    $this->student = User::factory()->create([
        'email_verified_at' => now(),
        'phone' => '52000000',
        'grade_level' => 'grade_12_science',
    ]);
    $this->student->assignRole('student');
    $this->parent = User::factory()->create(['email_verified_at' => now()]);
    $this->parent->assignRole('parent');

    ParentStudentLink::create([
        'parent_user_id' => $this->parent->id,
        'student_user_id' => $this->student->id,
        'relationship' => 'father',
        'verified_at' => now(),
    ]);

    $grade = GradeLevel::where('key', 'grade_12_science')->firstOrFail();
    $subject = Subject::factory()->create();
    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => $subject->id,
        'grade_level_id' => $grade->id,
        'accepts_private' => true,
        'private_monthly_price' => 90_000,
    ]);
    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'monthly_price' => 45_000,
        'capacity' => 10,
    ]);
});

it('lets a parent link an existing student by the student phone number', function (): void {
    ParentStudentLink::where('parent_user_id', $this->parent->id)
        ->where('student_user_id', $this->student->id)
        ->delete();

    expect(Route::has('parent.link-student'))->toBeTrue();

    $this->actingAs($this->parent)
        ->post(route('parent.link-student'), [
            'student_phone' => $this->student->phone,
            'relationship' => 'father',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('parent_student_links', [
        'parent_user_id' => $this->parent->id,
        'student_user_id' => $this->student->id,
        'relationship' => 'father',
    ]);
});

it('rejects a parent link request for a non-student phone', function (): void {
    $this->actingAs($this->parent)
        ->post(route('parent.link-student'), [
            'student_phone' => $this->teacher->phone,
            'relationship' => 'guardian',
        ])
        ->assertSessionHasErrors('student_phone');
});

it('lets one parent select different children and charges the selected child', function (): void {
    $secondStudent = User::factory()->create([
        'email_verified_at' => now(),
        'grade_level' => $this->student->grade_level,
    ]);
    $secondStudent->assignRole('student');

    ParentStudentLink::create([
        'parent_user_id' => $this->parent->id,
        'student_user_id' => $secondStudent->id,
        'relationship' => 'father',
        'verified_at' => now(),
    ]);

    $this->actingAs($this->parent)
        ->get(route('parent.dashboard', ['student_id' => $secondStudent->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selectedStudent.student.id', $secondStudent->id)
            ->where('links', fn ($links) => count($links) === 2));

    $this->actingAs($this->parent)
        ->post(route('parent.groups.subscribe', $this->group->id), [
            'student_id' => $secondStudent->id,
        ])
        ->assertRedirect();

    expect(Subscription::where('student_id', $secondStudent->id)->exists())->toBeTrue()
        ->and(Subscription::where('student_id', $this->student->id)->exists())->toBeFalse()
        ->and($this->parent->children()->pluck('users.id')->all())->toContain($secondStudent->id, $this->student->id);
});

it('shows a linked parent attendance, assessment, quiz and payment-ready dashboard', function (): void {
    $term = AcademicTerm::currentOrNext() ?? AcademicTerm::firstOrFail();
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $term->id,
    ]);
    $quiz = Quiz::factory()->withQuestions(1)->create(['curriculum_unit_id' => $unit->id]);
    QuizAttempt::factory()->submitted(80)->create([
        'user_id' => $this->student->id,
        'quiz_id' => $quiz->id,
        'earned_points' => 4,
        'total_points' => 5,
    ]);
    $worksheet = Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'title' => 'واجب الوحدة',
        'file_path' => '/storage/homework.pdf',
        'type' => Worksheet::TYPE_HOMEWORK,
        'max_score' => 20,
    ]);
    WorksheetSubmission::create([
        'worksheet_id' => $worksheet->id,
        'student_id' => $this->student->id,
        'submitted_file_path' => '/storage/submission.pdf',
        'score' => 18,
        'teacher_feedback' => 'أداء ممتاز',
        'submitted_at' => now()->subDay(),
        'graded_at' => now(),
    ]);
    $subscription = Subscription::factory()->active()->create([
        'student_id' => $this->student->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $this->group->id,
    ]);
    LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'الحصة الأولى',
        'scheduled_at' => now()->subDay(),
        'started_at' => now()->subDay()->addMinutes(2),
        'ended_at' => now()->subDay()->addHour(),
        'status' => LiveSession::STATUS_ENDED,
    ])->attendees()->create([
        'user_id' => $this->student->id,
        'joined_at' => now()->subDay()->addMinutes(5),
        'left_at' => now()->subDay()->addMinutes(55),
    ]);

    $this->actingAs($this->parent)
        ->get(route('parent.dashboard', ['student_id' => $this->student->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selectedStudent.student.id', $this->student->id)
            ->where('selectedStudent.attendanceSummary.present', 1)
            ->where('selectedStudent.attendanceSummary.rate', 100)
            ->where('selectedStudent.quizAttempts.0.earned_points', 4)
            ->where('selectedStudent.submissions.0.score', 18)
            ->where('selectedStudent.subscriptions.0.id', $subscription->id)
            ->has('selectedStudent.eligibleGroups')
            ->has('selectedStudent.freeIntroSlots')
            ->has('selectedStudent.privateAssignments'));
});

it('lets a linked parent reserve a group, pay through checkout, and book a free intro for the student', function (): void {
    $this->actingAs($this->parent)
        ->post(route('parent.groups.subscribe', $this->group->id), [
            'student_id' => $this->student->id,
        ])
        ->assertRedirect(route('checkout.show', Subscription::firstOrFail()->id));

    expect(Subscription::where('student_id', $this->student->id)->exists())->toBeTrue();

    $slot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $this->assignment->id,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHour(),
        'timezone' => 'Asia/Qatar',
        'is_free_intro' => true,
        'status' => 'available',
    ]);

    $this->actingAs($this->parent)
        ->post(route('parent.free-intro-sessions.book', $slot->id), [
            'student_id' => $this->student->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(SessionBooking::where('student_id', $this->student->id)
        ->where('private_session_slot_id', $slot->id)
        ->where('status', 'confirmed')
        ->exists())->toBeTrue();
});

it('lets a parent request private lessons and message both the teacher and administration', function (): void {
    Notification::fake();

    $this->actingAs($this->parent)
        ->post(route('parent.private-lesson-requests.store', $this->assignment->id), [
            'student_id' => $this->student->id,
        ])
        ->assertRedirect();

    $privateRequest = PrivateLessonRequest::firstOrFail();
    $conversation = Conversation::findOrFail($privateRequest->conversation_id);

    expect($conversation->participants()->whereKey($this->parent->id)->exists())->toBeTrue();

    $this->actingAs($this->parent)
        ->post(route('chat.start'), [
            'kind' => 'support',
            'student_id' => $this->student->id,
        ])
        ->assertRedirect();

    $support = Conversation::where('kind', 'support')->firstOrFail();

    $this->actingAs($this->parent)
        ->get(route('chat.index', ['conversation' => $support->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeConversation.id', $support->id)
            ->where('activeConversation.subject', 'متابعة إدارية ومالية للطالب'));

    $this->actingAs($this->parent)
        ->post(route('chat.send'), [
            'conversation_id' => $support->id,
            'message' => 'أحتاج متابعة حالة الدفع والحضور.',
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->get(route('chat.index', ['conversation' => $support->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('activeConversation.id', $support->id));

    Notification::assertSentTo($this->teacher, GenericDatabaseNotification::class);
});

it('lets a parent pay for and book a published private appointment for the student', function (): void {
    $slot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $this->assignment->id,
        'starts_at' => now()->addDays(3)->setTime(18, 0),
        'ends_at' => now()->addDays(3)->setTime(19, 0),
        'timezone' => 'Asia/Qatar',
        'is_free_intro' => false,
        'status' => 'available',
    ]);

    $this->actingAs($this->parent)
        ->post(route('parent.private.subscribe', $this->assignment->id), [
            'student_id' => $this->student->id,
        ])
        ->assertRedirect();

    $subscription = Subscription::where('student_id', $this->student->id)
        ->where('type', Subscription::TYPE_PRIVATE)
        ->firstOrFail();
    app(SubscriptionService::class)->activate($subscription);

    $this->actingAs($this->parent)
        ->post(route('parent.private-slots.book', $slot->id), [
            'student_id' => $this->student->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($slot->fresh()->status)->toBe('booked')
        ->and(SessionBooking::where('student_id', $this->student->id)
            ->where('private_session_slot_id', $slot->id)
            ->where('status', 'confirmed')
            ->exists())->toBeTrue();
});

it('blocks an unrelated parent from booking or viewing another student data', function (): void {
    $otherParent = User::factory()->create(['email_verified_at' => now()]);
    $otherParent->assignRole('parent');

    $this->actingAs($otherParent)
        ->get(route('parent.dashboard', ['student_id' => $this->student->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('selectedStudent', null));

    $this->actingAs($otherParent)
        ->post(route('parent.groups.subscribe', $this->group->id), [
            'student_id' => $this->student->id,
        ])
        ->assertForbidden();
});
