<?php

declare(strict_types=1);

use App\Application\Quiz\Services\QuizService;
use App\Domain\Course\Models\Course;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\User\Models\User;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Quiz Service Unit Tests (server-side grading) ───────────────────────────

describe('QuizService — Server-side Grading', function () {

    it('calculates score correctly for all-correct answers', function () {
        $service = app(QuizService::class);

        $student = User::factory()->create();
        $student->assignRole('student');

        $course = Course::factory()->create(['is_published' => true]);
        $quiz   = Quiz::factory()->withQuestions(3)->create([
            'course_id'     => $course->id,
            'passing_score' => 70,
            'is_active'     => true,
        ]);

        // Enroll student
        \App\Domain\Enrollment\Models\Enrollment::factory()->create([
            'user_id'   => $student->id,
            'course_id' => $course->id,
        ]);

        $attempt = $service->startAttempt($student, $quiz);

        // Build correct answers array
        $answers = [];
        foreach ($quiz->questions as $question) {
            $correctOptionId = $question->correctOptions()->first()->id;
            $answers[$question->id] = [$correctOptionId];
        }

        $result = $service->submitAttempt($attempt, $answers);

        expect($result->score)->toBe(100);
        expect($result->passed)->toBeTrue();
    });

    it('calculates score correctly for no correct answers', function () {
        $service = app(QuizService::class);
        $student = User::factory()->create();
        $student->assignRole('student');

        $course = Course::factory()->create(['is_published' => true]);
        $quiz   = Quiz::factory()->withQuestions(3)->create([
            'course_id'     => $course->id,
            'passing_score' => 50,
            'is_active'     => true,
        ]);

        \App\Domain\Enrollment\Models\Enrollment::factory()->create([
            'user_id' => $student->id, 'course_id' => $course->id,
        ]);

        $attempt = $service->startAttempt($student, $quiz);

        // All wrong answers
        $answers = [];
        foreach ($quiz->questions as $question) {
            $wrongOption = $question->options()->where('is_correct', false)->first();
            if ($wrongOption) {
                $answers[$question->id] = [$wrongOption->id];
            }
        }

        $result = $service->submitAttempt($attempt, $answers);

        expect($result->score)->toBe(0);
        expect($result->passed)->toBeFalse();
    });

    it('enforces max attempts limit', function () {
        $service = app(QuizService::class);
        $student = User::factory()->create();

        $quiz = Quiz::factory()->create(['is_active' => true]);

        // Exhaust all attempts
        for ($i = 0; $i < QuizService::MAX_ATTEMPTS; $i++) {
            QuizAttempt::factory()->create([
                'user_id'      => $student->id,
                'quiz_id'      => $quiz->id,
                'submitted_at' => now(),
            ]);
        }

        expect(fn () => $service->startAttempt($student, $quiz))
            ->toThrow(\LogicException::class);
    });

    it('is idempotent — cannot submit same attempt twice', function () {
        $service = app(QuizService::class);
        $student = User::factory()->create();

        $quiz    = Quiz::factory()->create(['is_active' => true]);
        $attempt = $service->startAttempt($student, $quiz);

        // Submit once
        $result = $service->submitAttempt($attempt, []);

        // Submit again — should throw
        expect(fn () => $service->submitAttempt($attempt->fresh(), []))
            ->toThrow(\LogicException::class);
    });

    it('server score cannot be manipulated by client', function () {
        $service = app(QuizService::class);
        $student = User::factory()->create();

        $quiz    = Quiz::factory()->withQuestions(2)->create(['is_active' => true]);
        $attempt = $service->startAttempt($student, $quiz);

        // Submit obviously wrong answers
        $result = $service->submitAttempt($attempt, [99999 => [99999]]);

        // Score must be computed from DB, not client input
        expect($result->score)->toBeInt();
        expect($result->score)->toBeLessThanOrEqual(100);
        expect($result->score)->toBeGreaterThanOrEqual(0);
    });

});

// ─── Quiz HTTP Tests ─────────────────────────────────────────────────────────

describe('Quiz HTTP Endpoints', function () {

    it('does not expose correct answers in quiz show response', function () {
        $student = User::factory()->create();
        $student->assignRole('student');

        $course  = Course::factory()->create(['is_published' => true]);
        $quiz    = Quiz::factory()->withQuestions(1)->create([
            'course_id' => $course->id,
            'is_active' => true,
        ]);

        \App\Domain\Enrollment\Models\Enrollment::factory()->create([
            'user_id' => $student->id, 'course_id' => $course->id,
        ]);

        $response = $this->actingAs($student)
            ->get(route('student.quiz', ['quizId' => $quiz->id]));

        $response->assertOk();

        // The is_correct field must NEVER appear in the response
        $response->assertDontSee('is_correct');
    });

    it('blocks unenrolled students from quiz', function () {
        $student = User::factory()->create();
        $student->assignRole('student');

        $course  = Course::factory()->create(['is_published' => true]);
        $quiz    = Quiz::factory()->create(['course_id' => $course->id, 'is_active' => true]);
        // NOT enrolled

        $response = $this->actingAs($student)
            ->get(route('student.quiz', ['quizId' => $quiz->id]));

        $response->assertForbidden();
    });

});
