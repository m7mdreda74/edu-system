<?php

declare(strict_types=1);

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

abstract class LiveSessionImprovementsTestCase extends TestCase
{
    public User $teacher;
    public User $student;
    public AcademicTerm $term;
    public TeachingAssignment $assignment;
    public TeachingGroup $group;
    public LiveSession $session;
}

uses(LiveSessionImprovementsTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    /** @var LiveSessionImprovementsTestCase $this */
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->teacher = User::factory()->create(['email_verified_at' => now()]);
    $this->teacher->assignRole('teacher');
    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->student->assignRole('student');
    $this->term = AcademicTerm::query()->firstOrFail();

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => Subject::query()->firstOrFail()->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $this->term->id,
    ]);
    Subscription::factory()->active()->create([
        'student_id' => $this->student->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $this->group->id,
    ]);
    SessionBooking::create([
        'student_id' => $this->student->id,
        'teaching_group_id' => $this->group->id,
        'status' => 'confirmed',
        'booked_at' => now(),
    ]);
    $this->session = LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'شرح تجريبي للحصة',
        'description' => 'وصف تجريبي للحصة',
        'scheduled_at' => now()->addDay(),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);
});

it('allows teacher to update a scheduled session title and description before starting', function (): void {
    /** @var LiveSessionImprovementsTestCase $this */
    $this->actingAs($this->teacher)
        ->patch(route('teacher.live-sessions.update', $this->session->id), [
            'title' => 'عنوان معدل للحصة المباشرة',
            'description' => 'وصف معدل للحصة المباشرة',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->session->fresh()->title)->toBe('عنوان معدل للحصة المباشرة')
        ->and($this->session->fresh()->description)->toBe('وصف معدل للحصة المباشرة');
});

it('rejects editing a session that has already ended or cancelled', function (): void {
    /** @var LiveSessionImprovementsTestCase $this */
    $this->session->update(['status' => LiveSession::STATUS_ENDED]);

    $this->actingAs($this->teacher)
        ->patch(route('teacher.live-sessions.update', $this->session->id), [
            'title' => 'محاولة تعديل غير مسموحة',
        ])
        ->assertStatus(422);
});

it('tracks student attendance heartbeat and closes stale attendees at last_heartbeat_at', function (): void {
    /** @var LiveSessionImprovementsTestCase $this */
    $this->session->update([
        'status' => LiveSession::STATUS_LIVE,
        'started_at' => now()->subHours(2),
    ]);

    // Student joins
    $this->actingAs($this->student)
        ->postJson(route('live-sessions.attendance.join', $this->session->id))
        ->assertOk()
        ->assertJson(['joined' => true]);

    $attendee = LiveSessionAttendee::where('live_session_id', $this->session->id)
        ->where('user_id', $this->student->id)
        ->firstOrFail();

    expect($attendee->last_heartbeat_at)->not->toBeNull();

    // Student sends heartbeat
    $this->travel(10)->minutes();
    $this->actingAs($this->student)
        ->postJson(route('live-sessions.attendance.heartbeat', $this->session->id))
        ->assertOk()
        ->assertJson(['alive' => true]);

    $heartbeatTime = $attendee->fresh()->last_heartbeat_at;
    expect($heartbeatTime)->not->toBeNull();

    // Student abruptly drops connection (no more heartbeats for 50 minutes)
    $this->travel(50)->minutes();

    // Teacher ends the session
    $this->actingAs($this->teacher)
        ->patch(route('teacher.live-sessions.status', $this->session->id), [
            'status' => LiveSession::STATUS_ENDED,
        ])
        ->assertRedirect();

    $freshAttendee = $attendee->fresh();
    expect($freshAttendee->left_at)->not->toBeNull()
        ->and($freshAttendee->left_at->equalTo($heartbeatTime))->toBeTrue();
});

it('publishes recording to the specifically selected curriculum unit', function (): void {
    /** @var LiveSessionImprovementsTestCase $this */
    $this->session->update([
        'status' => LiveSession::STATUS_LIVE,
        'started_at' => now()->subHour(),
    ]);

    $unit2 = CurriculumUnit::create([
        'teaching_assignment_id' => $this->assignment->id,
        'academic_term_id' => $this->term->id,
        'title' => 'الوحدة الثانية - القواعد النحوية',
        'order' => 2,
        'is_published' => true,
    ]);

    $recordingUrl = 'https://youtu.be/dQw4w9WgXcQ';

    $this->actingAs($this->teacher)
        ->patch(route('teacher.live-sessions.status', $this->session->id), [
            'status' => LiveSession::STATUS_ENDED,
            'recording_url' => $recordingUrl,
            'curriculum_unit_id' => $unit2->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->session->refresh();
    $material = GroupMaterial::findOrFail($this->session->lesson_id);

    expect($this->session->is_published_as_lesson)->toBeTrue()
        ->and($material->curriculum_unit_id)->toBe($unit2->id)
        ->and($material->video_url)->toBe($recordingUrl);
});
