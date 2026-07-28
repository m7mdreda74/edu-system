<?php

declare(strict_types=1);

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

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

it('accepts open-format lesson files homework and a paper unit exam', function () {
    Storage::fake('public');

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

    expect($lesson->attachment_path)->toStartWith('/storage/booklets/')
        ->and($homework->max_score)->toBe(20)
        ->and($homework->requires_submission)->toBeTrue()
        ->and($paperExam->max_score)->toBe(40);

    Storage::disk('public')->assertExists(substr($lesson->attachment_path, strlen('/storage/')));
    Storage::disk('public')->assertExists(substr($homework->file_path, strlen('/storage/')));
    Storage::disk('public')->assertExists(substr($paperExam->file_path, strlen('/storage/')));
});

it('does not write curriculum uploads to the read-only Vercel filesystem', function () {
    Storage::fake('public');
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
            'booklet' => UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('booklet');

    expect($lesson->refresh()->attachment_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
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
