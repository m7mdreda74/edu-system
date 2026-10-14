<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\TeacherReview;
use App\Domain\Payment\Models\Payment;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('teacher', 'web');
    Role::findOrCreate('student', 'web');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->teacher = User::factory()->create();
    $this->teacher->assignRole('teacher');
    $this->student = User::factory()->create();
    $this->student->assignRole('student');

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => Subject::query()->firstOrFail()->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
    ]);

    Cache::forget('admin.analytics.v1');
});

it('renders aggregated analytics for administrators', function (): void {
    $subscription = Subscription::factory()->active()->create([
        'student_id' => $this->student->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $this->group->id,
    ]);

    Payment::factory()->paid()->create([
        'user_id' => $this->student->id,
        'subscription_id' => $subscription->id,
        'amount' => 12_345,
        'paid_at' => now()->subMonth(),
    ]);

    TeacherReview::create([
        'user_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'rating' => 5,
        'is_approved' => true,
    ]);

    LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'Analytics test session',
        'scheduled_at' => now()->setHour(19)->setMinute(0),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Analytics')
            ->where('analytics.revenue.total', 12_345)
            ->where('analytics.top_groups.0.revenue', 12_345)
            ->where('analytics.ratings.0.average', 5)
            ->where('analytics.completion.rate', 0)
            ->has('analytics.revenue.months', 12)
            ->has('analytics.peak_sessions'));
});

it('calculates three-month first-purchase churn without counting later purchases', function (): void {
    $churnedStudent = User::factory()->create();
    $churnedStudent->assignRole('student');

    $firstPeriodStart = now()->subMonths(6)->toDateString();
    $firstPeriodEnd = now()->subMonths(5)->toDateString();

    Subscription::factory()->expired()->create([
        'student_id' => $churnedStudent->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $this->group->id,
        'period_start' => $firstPeriodStart,
        'period_end' => $firstPeriodEnd,
    ]);

    $secondGroup = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
    ]);
    Subscription::factory()->expired()->create([
        'student_id' => $churnedStudent->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $secondGroup->id,
        'period_start' => $firstPeriodStart,
        'period_end' => $firstPeriodEnd,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.analytics'))
        ->assertInertia(fn ($page) => $page
            ->where('analytics.churn.eligible_students', 1)
            ->where('analytics.churn.churned_students', 1)
            ->where('analytics.churn.rate', 100));
});

it('keeps analytics away from non-administrators', function (): void {
    $this->actingAs($this->teacher)
        ->get(route('admin.analytics'))
        ->assertForbidden();
});
