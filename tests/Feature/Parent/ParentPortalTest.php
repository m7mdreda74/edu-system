<?php

declare(strict_types=1);

use App\Application\Subscription\Services\SubscriptionService;
use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\LearningOutcome;
use App\Domain\Academic\Models\Subject;
use App\Domain\Communication\Models\Conversation;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LessonProgress;
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
use App\Domain\Subscription\Models\PurchaseRequest;
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

it('does not reveal whether a phone belongs to a student account', function (): void {
    $this->actingAs($this->parent)
        ->post(route('parent.link-student'), [
            'student_phone' => $this->teacher->phone,
            'relationship' => 'guardian',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(ParentStudentLink::count())->toBe(1);
});

it('lets a parent reject a pending purchase request and removes it from the pending state', function (): void {
    $request = PurchaseRequest::create([
        'student_user_id' => $this->student->id,
        'parent_user_id' => $this->parent->id,
        'teaching_group_id' => $this->group->id,
        'status' => PurchaseRequest::STATUS_PENDING,
    ]);

    $this->actingAs($this->parent)
        ->post(route('parent.purchase-requests.reject', $request->id), [
            'notes' => 'لا أريد التجديد الآن.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(PurchaseRequest::STATUS_REJECTED)
        ->and($request->fresh()->notes)->toBe('لا أريد التجديد الآن.');
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
        'teacher_feedback' => 'راجع السؤال الثاني.',
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
            ->where('selectedStudent.quizAttempts.0.teacher_feedback', 'راجع السؤال الثاني.')
            ->where('selectedStudent.submissions.0.score', 18)
            ->where('selectedStudent.subscriptions.0.id', $subscription->id)
            ->has('selectedStudent.eligibleGroups')
            ->has('selectedStudent.freeIntroSlots')
            ->has('selectedStudent.privateAssignments'));
});

it('shows linked parents active homework status without exposing a student submission action', function (): void {
    $term = AcademicTerm::currentOrNext() ?? AcademicTerm::firstOrFail();
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $term->id,
        'is_published' => true,
    ]);
    Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'title' => 'واجب متأخر للمتابعة',
        'file_path' => '/storage/homework-late.pdf',
        'type' => Worksheet::TYPE_HOMEWORK,
        'requires_submission' => true,
        'due_date' => today()->subDay(),
        'max_score' => 20,
    ]);
    Subscription::factory()->active()->create([
        'student_id' => $this->student->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $this->group->id,
    ]);

    $this->actingAs($this->parent)
        ->get(route('parent.dashboard', ['student_id' => $this->student->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selectedStudent.assignmentSummary.late', 1)
            ->where('selectedStudent.assignments.0.status', 'late')
            ->where('selectedStudent.assignments.0.submission', null)
            ->has('selectedStudent.assignments', 1));
});

it('shows a linked parent a seven-day report from the selected students real records', function (): void {
    $term = AcademicTerm::currentOrNext() ?? AcademicTerm::firstOrFail();
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $term->id,
        'is_published' => true,
    ]);
    $lesson = GroupMaterial::factory()->create(['curriculum_unit_id' => $unit->id]);
    LessonProgress::create([
        'student_id' => $this->student->id,
        'lesson_id' => $lesson->id,
        'watched_seconds' => 1_800,
        'is_completed' => true,
    ]);
    $worksheet = Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'lesson_id' => $lesson->id,
        'title' => 'واجب التقرير الأسبوعي',
        'file_path' => '/storage/weekly-homework.pdf',
        'type' => Worksheet::TYPE_HOMEWORK,
        'max_score' => 20,
    ]);
    WorksheetSubmission::create([
        'worksheet_id' => $worksheet->id,
        'student_id' => $this->student->id,
        'submitted_file_path' => '/storage/weekly-submission.pdf',
        'score' => 18,
        'submitted_at' => now()->subDay(),
        'graded_at' => now(),
    ]);
    $quiz = Quiz::factory()->withQuestions(1)->create(['curriculum_unit_id' => $unit->id]);
    QuizAttempt::factory()->submitted(85)->create([
        'user_id' => $this->student->id,
        'quiz_id' => $quiz->id,
        'earned_points' => 17,
        'total_points' => 20,
        'submitted_at' => now()->subDay(),
    ]);
    Subscription::factory()->active()->create([
        'student_id' => $this->student->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $this->group->id,
    ]);
    $session = LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'حصة التقرير الأسبوعي',
        'scheduled_at' => now()->subDay(),
        'started_at' => now()->subDay()->addMinutes(2),
        'ended_at' => now()->subDay()->addHour(),
        'status' => LiveSession::STATUS_ENDED,
    ]);
    $session->attendees()->create([
        'user_id' => $this->student->id,
        'joined_at' => now()->subDay()->addMinutes(5),
        'left_at' => now()->subDay()->addMinutes(55),
    ]);

    $this->actingAs($this->parent)
        ->get(route('parent.dashboard', ['student_id' => $this->student->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selectedStudent.weeklyReport.range.days', 7)
            ->where('selectedStudent.weeklyReport.summary.lessons_completed', 1)
            ->where('selectedStudent.weeklyReport.summary.study_minutes', 30)
            ->where('selectedStudent.weeklyReport.summary.assignments_submitted', 1)
            ->where('selectedStudent.weeklyReport.summary.assignments_graded', 1)
            ->where('selectedStudent.weeklyReport.summary.quizzes_submitted', 1)
            ->where('selectedStudent.weeklyReport.summary.average_quiz_score', 85)
            ->where('selectedStudent.weeklyReport.summary.attendance_rate', 100)
            ->where('selectedStudent.weeklyReport.comparison.previous_range.days', 7)
            ->where('selectedStudent.weeklyReport.comparison.metrics.study_minutes.direction', 'up')
            ->where('selectedStudent.weeklyReport.insights', [])
            ->where('selectedStudent.weeklyReport.daily', fn ($days): bool => count($days) === 7)
            ->where('selectedStudent.weeklyReport.by_subject.0.name', $this->assignment->subject?->name)
            ->has('selectedStudent.weeklyReport.recent', 4));
});

it('explains parent progress changes against the previous seven-day period', function (): void {
    $term = AcademicTerm::currentOrNext() ?? AcademicTerm::firstOrFail();
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $term->id,
        'is_published' => true,
    ]);
    $previousLesson = GroupMaterial::factory()->create(['curriculum_unit_id' => $unit->id]);
    $currentLesson = GroupMaterial::factory()->create(['curriculum_unit_id' => $unit->id]);

    $previousProgress = LessonProgress::create([
        'student_id' => $this->student->id,
        'lesson_id' => $previousLesson->id,
        'watched_seconds' => 1_200,
        'is_completed' => true,
    ]);
    $previousProgress->forceFill(['updated_at' => now()->subDays(8)])->saveQuietly();

    $currentProgress = LessonProgress::create([
        'student_id' => $this->student->id,
        'lesson_id' => $currentLesson->id,
        'watched_seconds' => 600,
        'is_completed' => true,
    ]);
    $currentProgress->forceFill(['updated_at' => now()->subDay()])->saveQuietly();

    $quiz = Quiz::factory()->withQuestions(1)->create(['curriculum_unit_id' => $unit->id]);
    $previousAttempt = QuizAttempt::factory()->submitted(90)->create([
        'user_id' => $this->student->id,
        'quiz_id' => $quiz->id,
        'earned_points' => 18,
        'total_points' => 20,
        'submitted_at' => now()->subDays(8),
    ]);
    $previousAttempt->forceFill(['submitted_at' => now()->subDays(8)])->saveQuietly();
    QuizAttempt::factory()->submitted(60)->create([
        'user_id' => $this->student->id,
        'quiz_id' => $quiz->id,
        'earned_points' => 12,
        'total_points' => 20,
        'submitted_at' => now()->subDay(),
    ]);

    Subscription::factory()->active()->create([
        'student_id' => $this->student->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $this->group->id,
    ]);

    $this->actingAs($this->parent)
        ->get(route('parent.dashboard', ['student_id' => $this->student->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selectedStudent.weeklyReport.comparison.metrics.study_minutes.current', 10)
            ->where('selectedStudent.weeklyReport.comparison.metrics.study_minutes.previous', 20)
            ->where('selectedStudent.weeklyReport.comparison.metrics.study_minutes.delta', -10)
            ->where('selectedStudent.weeklyReport.comparison.metrics.study_minutes.direction', 'down')
            ->where('selectedStudent.weeklyReport.comparison.metrics.average_quiz_score.current', 60)
            ->where('selectedStudent.weeklyReport.comparison.metrics.average_quiz_score.previous', 90)
            ->where('selectedStudent.weeklyReport.insights.0.key', 'study_minutes')
            ->where('selectedStudent.weeklyReport.insights.1.key', 'average_quiz_score'));
});

it('shows a parent explainable outcome progress from the selected students active curriculum only', function (): void {
    $term = AcademicTerm::currentOrNext() ?? AcademicTerm::firstOrFail();
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $term->id,
        'is_published' => true,
    ]);
    $lesson = GroupMaterial::factory()->create([
        'curriculum_unit_id' => $unit->id,
        'title' => 'درس الناتج المكتمل',
    ]);
    $quiz = Quiz::factory()->withQuestions(1)->create([
        'curriculum_unit_id' => $unit->id,
        'title' => 'اختبار إتقان الناتج',
    ]);
    LearningOutcome::create([
        'teaching_assignment_id' => $this->assignment->id,
        'curriculum_unit_id' => $unit->id,
        'group_material_id' => $lesson->id,
        'title' => 'إتمام الدرس',
        'is_published' => true,
    ]);
    LearningOutcome::create([
        'teaching_assignment_id' => $this->assignment->id,
        'curriculum_unit_id' => $unit->id,
        'quiz_id' => $quiz->id,
        'title' => 'إتقان الاختبار',
        'is_published' => true,
    ]);
    LessonProgress::create([
        'student_id' => $this->student->id,
        'lesson_id' => $lesson->id,
        'watched_seconds' => 1_800,
        'is_completed' => true,
    ]);
    QuizAttempt::factory()->submitted(90)->create([
        'user_id' => $this->student->id,
        'quiz_id' => $quiz->id,
        'earned_points' => 9,
        'total_points' => 10,
    ]);
    Subscription::factory()->active()->create([
        'student_id' => $this->student->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $this->group->id,
    ]);

    $unpublishedUnit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $term->id,
        'is_published' => false,
    ]);
    LearningOutcome::create([
        'teaching_assignment_id' => $this->assignment->id,
        'curriculum_unit_id' => $unpublishedUnit->id,
        'title' => 'ناتج مسودة لا يظهر',
        'is_published' => true,
    ]);

    $this->actingAs($this->parent)
        ->get(route('parent.dashboard', ['student_id' => $this->student->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('selectedStudent.learningOutcomes', 1)
            ->where('selectedStudent.learningOutcomes.0.id', $unit->id)
            ->where('selectedStudent.learningOutcomes.0.total_count', 2)
            ->where('selectedStudent.learningOutcomes.0.completed_count', 2)
            ->where('selectedStudent.learningOutcomes.0.progress_percent', 100)
            ->where('selectedStudent.learningOutcomes.0.outcomes.0.status.key', 'completed')
            ->where('selectedStudent.learningOutcomes.0.outcomes.1.status.key', 'mastered'));
});

it('lets a linked parent reserve a group, pay through checkout, and book a free intro for the student', function (): void {
    $response = $this->actingAs($this->parent)
        ->post(route('parent.groups.subscribe', $this->group->id), [
            'student_id' => $this->student->id,
        ]);

    $subscription = Subscription::query()
        ->where('student_id', $this->student->id)
        ->where('teaching_group_id', $this->group->id)
        ->latest('id')
        ->firstOrFail();

    $response->assertRedirect(route('checkout.show', $subscription->id));

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
            'recipient_id' => $this->admin->id,
        ])
        ->assertRedirect();

    $support = Conversation::query()
        ->where('kind', 'support')
        ->whereHas('participants', fn ($query) => $query->where('users.id', $this->parent->id))
        ->latest('id')
        ->firstOrFail();

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
