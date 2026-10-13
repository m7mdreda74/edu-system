<?php

declare(strict_types=1);

use App\Application\Learning\Services\AcademicAlertService;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Communication\Notifications\AcademicAlertNotification;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-13 09:00:00', 'UTC'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function academicAlertFixture(): array
{
    $teacher = User::factory()->create(['email_verified_at' => now()]);
    $teacher->assignRole('teacher');

    $student = User::factory()->create(['email_verified_at' => now()]);
    $student->assignRole('student');

    $verifiedParent = User::factory()->create(['email_verified_at' => now()]);
    $verifiedParent->assignRole('parent');

    $unverifiedParent = User::factory()->create(['email_verified_at' => now()]);
    $unverifiedParent->assignRole('parent');

    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $group = TeachingGroup::factory()->create(['teaching_assignment_id' => $assignment->id]);
    $unit = CurriculumUnit::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'is_published' => true,
    ]);

    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_assignment_id' => $assignment->id,
        'teaching_group_id' => $group->id,
        'period_end' => now()->addDays(30)->toDateString(),
    ]);

    ParentStudentLink::create([
        'parent_user_id' => $verifiedParent->id,
        'student_user_id' => $student->id,
        'relationship' => 'guardian',
        'status' => ParentStudentLink::STATUS_APPROVED,
        'verified_at' => now(),
    ]);
    ParentStudentLink::create([
        'parent_user_id' => $unverifiedParent->id,
        'student_user_id' => $student->id,
        'relationship' => 'guardian',
        'status' => ParentStudentLink::STATUS_PENDING,
        'verified_at' => null,
        'expires_at' => now()->addHour(),
    ]);

    return compact('teacher', 'student', 'verifiedParent', 'unverifiedParent', 'assignment', 'group', 'unit');
}

it('sends homework alerts to the student and verified parent only, once per cooldown', function (): void {
    $fixture = academicAlertFixture();
    $worksheet = Worksheet::create([
        'curriculum_unit_id' => $fixture['unit']->id,
        'title' => 'واجب المعادلات',
        'file_path' => 'private://worksheets/equations.pdf',
        'type' => Worksheet::TYPE_HOMEWORK,
        'requires_submission' => true,
        'due_date' => today()->addDay(),
        'max_score' => 20,
    ]);

    $first = app(AcademicAlertService::class)->send();

    expect($first['notifications_sent'])->toBe(2)
        ->and($fixture['student']->notifications()->count())->toBe(1)
        ->and($fixture['verifiedParent']->notifications()->count())->toBe(1)
        ->and($fixture['unverifiedParent']->notifications()->count())->toBe(0)
        ->and($fixture['student']->notifications()->first()->data['alert_type'])
        ->toBe(AcademicAlertService::ALERT_HOMEWORK_DEADLINE)
        ->and($fixture['student']->notifications()->first()->data['entity_id'])
        ->toBe($worksheet->id);

    $second = app(AcademicAlertService::class)->send();

    expect($second['notifications_sent'])->toBe(0)
        ->and($second['duplicates_skipped'])->toBe(2)
        ->and($fixture['student']->notifications()->count())->toBe(1)
        ->and($fixture['verifiedParent']->notifications()->count())->toBe(1);
});

it('covers upcoming classes and recent absences for confirmed bookings', function (): void {
    $fixture = academicAlertFixture();
    $upcoming = LiveSession::create([
        'teacher_id' => $fixture['teacher']->id,
        'teaching_group_id' => $fixture['group']->id,
        'title' => 'حصة المراجعة',
        'scheduled_at' => now()->addHours(3),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);
    SessionBooking::create([
        'student_id' => $fixture['student']->id,
        'teaching_group_id' => $fixture['group']->id,
        'status' => 'confirmed',
        'booked_at' => now(),
    ]);

    $upcomingStats = app(AcademicAlertService::class)->send();

    expect($upcomingStats['notifications_sent'])->toBe(2)
        ->and($fixture['student']->notifications()->first()->data['alert_type'])
        ->toBe(AcademicAlertService::ALERT_UPCOMING_CLASS);

    $upcoming->update([
        'scheduled_at' => now()->subDay(),
        'started_at' => now()->subDay(),
        'ended_at' => now()->subDay()->addHour(),
        'status' => LiveSession::STATUS_ENDED,
    ]);

    $absenceStats = app(AcademicAlertService::class)->send();

    expect($absenceStats['notifications_sent'])->toBe(2)
        ->and($fixture['student']->notifications()->pluck('data')->pluck('alert_type')->all())
        ->toContain(AcademicAlertService::ALERT_RECENT_ABSENCE)
        ->and($fixture['verifiedParent']->notifications()->count())->toBe(2);
});

it('flags a declining assessment trend and an expiring subscription', function (): void {
    $fixture = academicAlertFixture();
    $earlyDates = [now()->subDays(50), now()->subDays(45)];
    $recentDates = [now()->subDays(5), now()->subDays(2)];

    foreach ([90, 88] as $index => $score) {
        $quiz = Quiz::factory()->create(['curriculum_unit_id' => $fixture['unit']->id]);
        QuizAttempt::create([
            'user_id' => $fixture['student']->id,
            'quiz_id' => $quiz->id,
            'score' => $score,
            'passed' => true,
            'submitted_at' => $earlyDates[$index],
        ]);
    }

    foreach ([45, 40] as $index => $score) {
        $quiz = Quiz::factory()->create(['curriculum_unit_id' => $fixture['unit']->id]);
        QuizAttempt::create([
            'user_id' => $fixture['student']->id,
            'quiz_id' => $quiz->id,
            'score' => $score,
            'passed' => false,
            'submitted_at' => $recentDates[$index],
        ]);
    }

    $subscription = Subscription::query()->where('student_id', $fixture['student']->id)->firstOrFail();
    $subscription->update(['period_end' => now()->addDays(3)->toDateString()]);

    $stats = app(AcademicAlertService::class)->send();
    $types = $fixture['student']->notifications()->get()->pluck('data')->pluck('alert_type')->all();

    expect($stats['notifications_sent'])->toBe(4)
        ->and($types)->toContain(AcademicAlertService::ALERT_DECLINING_ASSESSMENT)
        ->and($types)->toContain(AcademicAlertService::ALERT_SUBSCRIPTION_EXPIRING)
        ->and($fixture['student']->notifications()->get()->every(
            fn ($notification): bool => $notification->type === AcademicAlertNotification::class,
        ))->toBeTrue();
});

it('protects the academic alert cron endpoint with the configured secret', function (): void {
    $fixture = academicAlertFixture();
    Worksheet::create([
        'curriculum_unit_id' => $fixture['unit']->id,
        'title' => 'واجب cron',
        'file_path' => 'private://worksheets/cron.pdf',
        'type' => Worksheet::TYPE_HOMEWORK,
        'requires_submission' => true,
        'due_date' => today()->addDay(),
        'max_score' => 20,
    ]);
    config()->set('services.vercel.cron_secret', 'academic-alert-secret');

    $this->getJson(route('cron.academic-alerts'))->assertUnauthorized();

    $this->withHeader('Authorization', 'Bearer academic-alert-secret')
        ->getJson(route('cron.academic-alerts'))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'notifications_sent' => 2,
        ]);
});
