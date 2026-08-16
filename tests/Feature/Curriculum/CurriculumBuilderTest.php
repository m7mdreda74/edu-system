<?php

declare(strict_types=1);

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Quiz\Models\QuizOption;
use App\Domain\Quiz\Models\QuizQuestion;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// Binds $this inside every Pest closure in this file to Tests\TestCase.
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->term = AcademicTerm::currentOrNext() ?? AcademicTerm::firstOrFail();
    $this->teacher = User::factory()->create(['email_verified_at' => now()]);
    $this->teacher->assignRole('teacher');

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => Subject::factory()->create()->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
});

it('generates a complete term shell with units lessons and draft unit exams', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.curriculum.skeleton', ['assignment' => $this->assignment->id]), [
            'academic_term_id' => $this->term->id,
            'units_count' => 3,
            'lessons_per_unit' => 4,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(CurriculumUnit::where('teaching_assignment_id', $this->assignment->id)->count())->toBe(3)
        ->and(GroupMaterial::count())->toBe(12)
        ->and(Quiz::count())->toBe(3)
        ->and(Quiz::where('is_active', false)->count())->toBe(3)
        ->and(CurriculumUnit::orderBy('order')->pluck('title')->all())->toBe([
            'الوحدة الأولى',
            'الوحدة الثانية',
            'الوحدة الثالثة',
        ]);
});

it('keeps another teacher out of the curriculum', function () {
    $otherTeacher = User::factory()->create(['email_verified_at' => now()]);
    $otherTeacher->assignRole('teacher');

    $this->actingAs($otherTeacher)
        ->get(route('teacher.curriculum', ['assignment' => $this->assignment->id]))
        ->assertForbidden();

    $this->actingAs($otherTeacher)
        ->post(route('teacher.curriculum.skeleton', ['assignment' => $this->assignment->id]), [
            'academic_term_id' => $this->term->id,
            'units_count' => 1,
            'lessons_per_unit' => 1,
        ])
        ->assertForbidden();
});

it('accepts safe lesson files homework and a paper unit exam', function () {
    Storage::fake('local');

    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $this->term->id,
    ]);
    $lesson = GroupMaterial::factory()->create(['curriculum_unit_id' => $unit->id]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.lessons.booklet', ['lesson' => $lesson->id]), [
            'booklet' => UploadedFile::fake()->create('ملزمة الدرس.odt', 40, 'application/vnd.oasis.opendocument.text'),
        ])
        ->assertRedirect();

    $this->actingAs($this->teacher)
        ->post(route('teacher.lessons.homework', ['lesson' => $lesson->id]), [
            'file' => UploadedFile::fake()->create('الواجب.zip', 50, 'application/zip'),
            'due_date' => now()->addWeek()->toDateString(),
            'max_score' => 20,
            'requires_submission' => true,
        ])
        ->assertRedirect();

    $this->actingAs($this->teacher)
        ->post(route('teacher.units.paper-exam', ['unit' => $unit->id]), [
            'file' => UploadedFile::fake()->create('اختبار الوحدة.doc', 60, 'application/msword'),
            'max_score' => 40,
            'requires_submission' => true,
        ])
        ->assertRedirect();

    $lesson->refresh();
    $homework = Worksheet::where('lesson_id', $lesson->id)
        ->where('type', Worksheet::TYPE_HOMEWORK)
        ->firstOrFail();
    $paperExam = Worksheet::where('curriculum_unit_id', $unit->id)
        ->where('type', Worksheet::TYPE_PAPER_EXAM)
        ->firstOrFail();

    expect($lesson->attachment_path)->toStartWith('private://booklets/')
        ->and($homework->max_score)->toBe(20)
        ->and($homework->requires_submission)->toBeTrue()
        ->and($paperExam->max_score)->toBe(40);

    Storage::disk('local')->assertExists(substr($lesson->attachment_path, strlen('private://')));
    Storage::disk('local')->assertExists(substr($homework->file_path, strlen('private://')));
    Storage::disk('local')->assertExists(substr($paperExam->file_path, strlen('private://')));
});

it('does not write curriculum uploads to the read-only Vercel filesystem', function () {
    Storage::fake('local');
    config()->set([
        'services.vercel_blob.enabled' => false,
        'services.vercel_blob.serverless' => true,
    ]);

    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $this->term->id,
    ]);
    $lesson = GroupMaterial::factory()->create(['curriculum_unit_id' => $unit->id]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.lessons.booklet', ['lesson' => $lesson->id]), [
            'booklet' => UploadedFile::fake()->create('lesson-notes.pdf', 40, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('booklet');

    expect($lesson->refresh()->attachment_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('authorizes and records a direct Blob booklet without touching the local disk', function () {
    config()->set([
        'services.vercel_blob.enabled' => true,
        'services.vercel_blob.store_id' => '1sxstfwepd7zn41q',
    ]);

    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $this->term->id,
    ]);
    $lesson = GroupMaterial::factory()->create(['curriculum_unit_id' => $unit->id]);
    $pathname = "curriculum/{$this->teacher->id}/booklet/{$lesson->id}/notes-oYnXSVczoLa9.pdf";
    $url = "https://1sxstfwepd7zn41q.public.blob.vercel-storage.com/{$pathname}";

    $this->actingAs($this->teacher)
        ->postJson(route('teacher.curriculum-uploads.authorize'), [
            'kind' => 'booklet',
            'target_id' => $lesson->id,
            'pathname' => $pathname,
            'file_size' => 1024,
        ])
        ->assertOk()
        ->assertJsonPath('teacher_id', $this->teacher->id)
        ->assertJsonPath('kind', 'booklet');

    $this->actingAs($this->teacher)
        ->post(route('teacher.lessons.booklet', $lesson->id), [
            'blob_url' => $url,
            'blob_pathname' => $pathname,
        ])
        ->assertRedirect();

    expect($lesson->refresh()->attachment_path)->toBe($url);
});

it('stores a timed multiple choice exam and enforces its answer key', function () {
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $this->term->id,
    ]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.quizzes.store', ['unit' => $unit->id]), [
            'title' => 'اختبار الوحدة الأولى',
            'time_limit_minutes' => 25,
            'available_from' => now()->addDay()->format('Y-m-d H:i:s'),
            'available_until' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'passing_score' => 70,
            'is_active' => true,
        ])
        ->assertRedirect();

    $quiz = Quiz::firstOrFail();

    $this->actingAs($this->teacher)
        ->post(route('teacher.quizzes.questions.store', ['quiz' => $quiz->id]), [
            'question_text' => 'أي الأعداد التالية أولية؟',
            'type' => 'multiple',
            'points' => 5,
            'options' => [
                ['option_text' => '2', 'is_correct' => true],
                ['option_text' => '3', 'is_correct' => true],
                ['option_text' => '4', 'is_correct' => false],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($quiz->refresh()->time_limit_minutes)->toBe(25)
        ->and($quiz->questions()->count())->toBe(1)
        ->and($quiz->questions()->firstOrFail()->points)->toBe(5)
        ->and($quiz->questions()->firstOrFail()->correctOptions()->count())->toBe(2);

    $this->actingAs($this->teacher)
        ->post(route('teacher.quizzes.questions.store', ['quiz' => $quiz->id]), [
            'question_text' => 'سؤال بلا مفتاح إجابة',
            'type' => 'single',
            'options' => [
                ['option_text' => 'أ', 'is_correct' => false],
                ['option_text' => 'ب', 'is_correct' => false],
            ],
        ])
        ->assertSessionHasErrors('options');
});

it('awards the configured points and keeps the pass result percentage-based', function () {
    Notification::fake();

    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $this->term->id,
    ]);
    $quiz = Quiz::factory()->create([
        'curriculum_unit_id' => $unit->id,
        'passing_score' => 70,
    ]);

    $first = QuizQuestion::create([
        'quiz_id' => $quiz->id,
        'question_text' => 'السؤال الأعلى نقاطًا',
        'type' => 'single',
        'points' => 9,
        'order' => 1,
    ]);
    $firstCorrect = QuizOption::create([
        'question_id' => $first->id,
        'option_text' => 'صحيح',
        'is_correct' => true,
    ]);
    QuizOption::create([
        'question_id' => $first->id,
        'option_text' => 'خطأ',
        'is_correct' => false,
    ]);

    $second = QuizQuestion::create([
        'quiz_id' => $quiz->id,
        'question_text' => 'سؤال بنقطة واحدة',
        'type' => 'single',
        'points' => 1,
        'order' => 2,
    ]);
    QuizOption::create([
        'question_id' => $second->id,
        'option_text' => 'صحيح',
        'is_correct' => true,
    ]);
    $secondWrong = QuizOption::create([
        'question_id' => $second->id,
        'option_text' => 'خطأ',
        'is_correct' => false,
    ]);

    $group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $this->term->id,
    ]);
    $student = User::factory()->create(['email_verified_at' => now()]);
    $student->assignRole('student');
    $parent = User::factory()->create(['email_verified_at' => now()]);
    $parent->assignRole('parent');
    ParentStudentLink::create([
        'parent_user_id' => $parent->id,
        'student_user_id' => $student->id,
        'relationship' => 'father',
        'verified_at' => now(),
    ]);
    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $group->id,
    ]);

    $attemptId = $this->actingAs($student)
        ->postJson(route('student.quiz.start', $quiz->id))
        ->assertOk()
        ->json('attempt_id');

    $this->actingAs($student)
        ->postJson(route('student.quiz.submit', $quiz->id), [
            'attempt_id' => $attemptId,
            'answers' => [
                $first->id => [$firstCorrect->id],
                $second->id => [$secondWrong->id],
            ],
        ])
        ->assertOk()
        ->assertJson([
            'score' => 90,
            'earned_points' => 9,
            'total_points' => 10,
            'passed' => true,
        ]);

    Notification::assertSentTo($parent, GenericDatabaseNotification::class, function ($notification) use ($parent, $student): bool {
        $data = $notification->toArray($parent);

        return str_contains($data['message'], $student->name)
            && str_contains($data['message'], '90%')
            && $data['link'] === route('parent.dashboard', ['student_id' => $student->id]);
    });

    expect(QuizAttempt::findOrFail($attemptId))
        ->earned_points->toBe(9)
        ->total_points->toBe(10)
        ->score->toBe(90)
        ->passed->toBeTrue();
});

it('sends a paper unit exam grade and teacher feedback to the student and verified parent', function () {
    Notification::fake();

    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $this->term->id,
    ]);
    $paperExam = Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'title' => 'النموذج الورقي للوحدة الأولى',
        'file_path' => '/storage/paper-exams/unit-one.pdf',
        'type' => Worksheet::TYPE_PAPER_EXAM,
        'requires_submission' => true,
        'max_score' => 40,
    ]);
    $student = User::factory()->create();
    $student->assignRole('student');
    $parent = User::factory()->create();
    $parent->assignRole('parent');
    ParentStudentLink::create([
        'parent_user_id' => $parent->id,
        'student_user_id' => $student->id,
        'relationship' => 'parent',
        'verified_at' => now(),
    ]);
    $submission = WorksheetSubmission::create([
        'worksheet_id' => $paperExam->id,
        'student_id' => $student->id,
        'submitted_file_path' => '/storage/submissions/student-answer.pdf',
        'submitted_at' => now(),
    ]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.worksheets.grade', $submission->id), [
            'score' => 34,
            'teacher_feedback' => 'مستوى ممتاز مع مراجعة السؤال الأخير.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($student, GenericDatabaseNotification::class);
    Notification::assertSentTo($parent, GenericDatabaseNotification::class, function ($notification) use ($parent): bool {
        $data = $notification->toArray($parent);

        return str_contains($data['message'], '34/40')
            && str_contains($data['message'], 'مستوى ممتاز');
    });

    expect($submission->fresh())
        ->score->toBe(34)
        ->teacher_feedback->toBe('مستوى ممتاز مع مراجعة السؤال الأخير.')
        ->graded_at->not->toBeNull();
});

it('sends homework grades to every verified parent too', function () {
    Notification::fake();

    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $this->term->id,
    ]);
    $homework = Worksheet::create([
        'curriculum_unit_id' => $unit->id,
        'title' => 'واجب الدرس الأول',
        'file_path' => '/storage/homework/unit-one.pdf',
        'type' => Worksheet::TYPE_HOMEWORK,
        'requires_submission' => true,
        'max_score' => 20,
    ]);
    $student = User::factory()->create();
    $student->assignRole('student');
    $parent = User::factory()->create();
    $parent->assignRole('parent');
    ParentStudentLink::create([
        'parent_user_id' => $parent->id,
        'student_user_id' => $student->id,
        'relationship' => 'mother',
        'verified_at' => now(),
    ]);
    $submission = WorksheetSubmission::create([
        'worksheet_id' => $homework->id,
        'student_id' => $student->id,
        'submitted_file_path' => '/storage/submissions/homework-answer.pdf',
        'submitted_at' => now(),
    ]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.worksheets.grade', $submission->id), [
            'score' => 18,
            'teacher_feedback' => 'أداء ممتاز.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($parent, GenericDatabaseNotification::class, function ($notification) use ($parent): bool {
        $data = $notification->toArray($parent);

        return $data['title'] === 'تقييم واجب جديد'
            && str_contains($data['message'], '18/20')
            && str_contains($data['message'], 'أداء ممتاز');
    });
});

it('withholds exam questions until a subscribed student starts inside the availability window', function () {
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $this->term->id,
    ]);
    $quiz = Quiz::factory()->withQuestions(1)->create([
        'curriculum_unit_id' => $unit->id,
        'available_from' => now()->addHour(),
        'available_until' => now()->addHours(2),
    ]);
    $group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $this->term->id,
    ]);
    $student = User::factory()->create(['email_verified_at' => now()]);
    $student->assignRole('student');

    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $group->id,
    ]);

    $this->actingAs($student)
        ->get(route('student.quiz', ['quizId' => $quiz->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Student/Quiz')
            ->where('quiz.questions_count', 1)
            ->has('questions', 0));

    $this->actingAs($student)
        ->postJson(route('student.quiz.start', ['quizId' => $quiz->id]))
        ->assertForbidden();

    $quiz->update([
        'available_from' => now()->subMinute(),
        'available_until' => now()->addHour(),
    ]);

    $this->actingAs($student)
        ->postJson(route('student.quiz.start', ['quizId' => $quiz->id]))
        ->assertOk()
        ->assertJsonCount(1, 'questions')
        ->assertJsonMissingPath('questions.0.options.0.is_correct');
});

it('cannot submit or record a violation against a different quiz route', function () {
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $this->term->id,
    ]);
    $firstQuiz = Quiz::factory()->withQuestions(1)->create(['curriculum_unit_id' => $unit->id]);
    $secondQuiz = Quiz::factory()->withQuestions(1)->create([
        'curriculum_unit_id' => $unit->id,
        'lesson_id' => GroupMaterial::factory()->create(['curriculum_unit_id' => $unit->id])->id,
    ]);
    $group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $this->term->id,
    ]);
    $student = User::factory()->create(['email_verified_at' => now()]);
    $student->assignRole('student');

    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $group->id,
    ]);

    $attempt = QuizAttempt::factory()->create([
        'user_id' => $student->id,
        'quiz_id' => $secondQuiz->id,
    ]);

    $this->actingAs($student)
        ->postJson(route('student.quiz.submit', ['quizId' => $firstQuiz->id]), [
            'attempt_id' => $attempt->id,
            'answers' => [999999 => [999999]],
        ])
        ->assertNotFound();

    $this->actingAs($student)
        ->postJson(route('student.quiz.violation', ['quizId' => $firstQuiz->id]), [
            'attempt_id' => $attempt->id,
        ])
        ->assertNotFound();
});
