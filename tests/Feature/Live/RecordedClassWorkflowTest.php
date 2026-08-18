<?php

declare(strict_types=1);

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

abstract class RecordedClassTestCase extends TestCase
{
    public User $admin;

    public User $teacher;

    public User $student;

    public AcademicTerm $term;

    public TeachingAssignment $assignment;

    public TeachingGroup $group;

    public Subscription $subscription;

    public LiveSession $session;
}

uses(RecordedClassTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    /** @var RecordedClassTestCase $this */
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
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
    $this->subscription = Subscription::factory()->active()->create([
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
        'title' => 'شرح الوحدة الأولى',
        'scheduled_at' => now()->subHour(),
        'started_at' => now()->subHour(),
        'status' => LiveSession::STATUS_LIVE,
    ]);
});

it('publishes a YouTube recording for in-platform playback and reserves deletion for admin', function (): void {
    /** @var RecordedClassTestCase $this */
    $this->actingAs($this->teacher)
        ->patch(route('teacher.live-sessions.status', $this->session->id), [
            'status' => LiveSession::STATUS_ENDED,
            'recording_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->session->refresh();
    $material = GroupMaterial::findOrFail($this->session->lesson_id);

    expect($this->session->is_published_as_lesson)->toBeTrue()
        ->and($material->video_url)->toBe('https://youtu.be/dQw4w9WgXcQ');

    $this->actingAs($this->student)
        ->getJson(route('student.video.url', $material->id))
        ->assertOk()
        ->assertJson([
            'provider' => 'youtube_proxy',
        ]);

    $signedUrl = $this->actingAs($this->student)
        ->getJson(route('student.video.url', $material->id))
        ->json('signed_url');

    expect($signedUrl)
        ->toContain('/youtube-stream/'.$material->id)
        ->not->toContain('youtu.be');

    $this->actingAs($this->teacher)
        ->delete(route('teacher.materials.destroy', $material->id))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->delete(route('admin.recorded-classes.destroy', $this->session->id))
        ->assertRedirect();

    expect(GroupMaterial::find($material->id))->toBeNull()
        ->and($this->session->fresh()->recording_url)->toBeNull()
        ->and($this->session->fresh()->is_published_as_lesson)->toBeFalse();
});

it('saves a Jitsi file recording link automatically and publishes it when the class ends', function (): void {
    /** @var RecordedClassTestCase $this */
    $recordingUrl = 'https://meet.jit.si/recordings/altafawwuq-'.$this->session->id.'.mp4';

    $this->actingAs($this->teacher)
        ->postJson(route('teacher.live-sessions.recording', $this->session->id), [
            'recording_url' => $recordingUrl,
        ])
        ->assertOk()
        ->assertJson([
            'saved' => true,
            'published' => false,
        ]);

    expect($this->session->fresh()->recording_url)->toBe($recordingUrl)
        ->and($this->session->fresh()->is_published_as_lesson)->toBeFalse();

    $this->actingAs($this->teacher)
        ->patch(route('teacher.live-sessions.status', $this->session->id), [
            'status' => LiveSession::STATUS_ENDED,
            'recording_url' => '',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->session->refresh();
    $material = GroupMaterial::findOrFail($this->session->lesson_id);

    expect($this->session->is_published_as_lesson)->toBeTrue()
        ->and($material->video_url)->toBe($recordingUrl);

    $this->actingAs($this->student)
        ->getJson(route('student.video.url', $material->id))
        ->assertOk()
        ->assertJson([
            'provider' => 'file',
        ]);
});

it('publishes a free intro recording as a public preview on the teacher profile', function (): void {
    /** @var RecordedClassTestCase $this */
    $slot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $this->assignment->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addMinutes(30),
        'timezone' => 'Asia/Qatar',
        'is_free_intro' => true,
        'status' => 'booked',
    ]);

    SessionBooking::create([
        'student_id' => $this->student->id,
        'private_session_slot_id' => $slot->id,
        'status' => 'confirmed',
        'booked_at' => now()->subDay(),
    ]);

    $freeSession = LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'private_session_slot_id' => $slot->id,
        'title' => 'الحصة التجريبية المجانية',
        'description' => 'شرح تعريفي',
        'scheduled_at' => now()->subHour(),
        'started_at' => now()->subHour(),
        'status' => LiveSession::STATUS_LIVE,
    ]);
    $recordingUrl = 'https://meet.jit.si/recordings/free-intro-'.$freeSession->id.'.mp4';

    $this->actingAs($this->teacher)
        ->postJson(route('teacher.live-sessions.recording', $freeSession->id), [
            'recording_url' => $recordingUrl,
        ])
        ->assertOk();

    $this->actingAs($this->teacher)
        ->patch(route('teacher.live-sessions.status', $freeSession->id), [
            'status' => LiveSession::STATUS_ENDED,
            'recording_url' => '',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $material = GroupMaterial::findOrFail($freeSession->fresh()->lesson_id);

    expect($material->is_free_preview)->toBeTrue();

    $this->get(route('teachers.show', $this->teacher->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('assignments.0.free_recordings.0.id', $material->id)
            ->where('assignments.0.free_recordings.0.title', 'الحصة التجريبية المجانية')
            ->where('assignments.0.free_recordings.0.stream_url', fn (string $url): bool => str_contains($url, '/free-recordings/'.$material->id.'/stream')));

    $signedUrl = URL::temporarySignedRoute(
        'public.free-recordings.stream',
        now()->addMinutes(5),
        ['materialId' => $material->id],
    );

    $this->get($signedUrl)->assertRedirect($recordingUrl);
    $this->get(route('public.free-recordings.stream', $material->id))->assertForbidden();
});

it('rejects a recording link from an untrusted host', function (): void {
    /** @var RecordedClassTestCase $this */
    $this->actingAs($this->teacher)
        ->postJson(route('teacher.live-sessions.recording', $this->session->id), [
            'recording_url' => 'https://evil.example/recording.mp4',
        ])
        ->assertUnprocessable();

    expect($this->session->fresh()->recording_url)->toBeNull();
});

it('lets the teacher save the attendance roll and rejects students outside the class', function (): void {
    /** @var RecordedClassTestCase $this */
    $outsider = User::factory()->create();
    $outsider->assignRole('student');

    $this->actingAs($this->teacher)
        ->post(route('teacher.live-sessions.attendance', $this->session->id), [
            'student_ids' => [$outsider->id],
        ])
        ->assertSessionHasErrors('student_ids');

    $this->actingAs($this->teacher)
        ->post(route('teacher.live-sessions.attendance', $this->session->id), [
            'student_ids' => [$this->student->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(LiveSessionAttendee::where('live_session_id', $this->session->id)
        ->where('user_id', $this->student->id)->exists())->toBeTrue();
});

it('authorizes Jitsi entry while the teacher controls attendance and closes it when the class ends', function (): void {
    /** @var RecordedClassTestCase $this */
    $this->actingAs($this->student)
        ->get(route('live-sessions.room', $this->session->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Live/LiveSessionRoom')
            ->where('jitsi.domain', 'meet.jit.si')
            ->where('jitsi.whiteboard.enabled', true));

    expect(LiveSessionAttendee::where('live_session_id', $this->session->id)
        ->where('user_id', $this->student->id)
        ->exists())->toBeFalse();

    $this->actingAs($this->teacher)
        ->post(route('teacher.live-sessions.attendance', $this->session->id), [
            'student_ids' => [$this->student->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $attendance = LiveSessionAttendee::where('live_session_id', $this->session->id)
        ->where('user_id', $this->student->id)
        ->firstOrFail();

    expect($attendance->joined_at)->not->toBeNull()
        ->and($attendance->left_at)->not->toBeNull();

    $this->actingAs($this->teacher)
        ->patch(route('teacher.live-sessions.status', $this->session->id), [
            'status' => LiveSession::STATUS_ENDED,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($attendance->fresh()->left_at)->not->toBeNull();

    $this->actingAs($this->teacher)
        ->get(route('teacher.live-sessions'))
        ->assertInertia(fn ($page) => $page
            ->where('attendanceReport.0.student.id', $this->student->id)
            ->where('attendanceReport.0.joined_at', fn ($value) => filled($value))
            ->where('attendanceReport.0.left_at', fn ($value) => filled($value)));

    $this->actingAs($this->admin)
        ->get(route('admin.reports'))
        ->assertInertia(fn ($page) => $page
            ->where('attendance.data.0.student.id', $this->student->id)
            ->where('attendance.data.0.joined_at', fn ($value) => filled($value))
            ->where('attendance.data.0.left_at', fn ($value) => filled($value)));
});

it('returns every report row when the admin requests the printable report', function (): void {
    /** @var RecordedClassTestCase $this */
    for ($index = 1; $index <= 21; $index++) {
        $session = LiveSession::create([
            'teacher_id' => $this->teacher->id,
            'teaching_group_id' => $this->group->id,
            'title' => 'حصة التقرير '.$index,
            'scheduled_at' => now()->subDays($index),
            'started_at' => now()->subDays($index),
            'ended_at' => now()->subDays($index)->addHour(),
            'status' => LiveSession::STATUS_ENDED,
        ]);

        $session->attendees()->create([
            'user_id' => $this->student->id,
            'joined_at' => now()->subDays($index)->addMinutes(5),
            'left_at' => now()->subDays($index)->addMinutes(50),
        ]);
    }

    $this->actingAs($this->admin)
        ->get(route('admin.reports', ['print' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('printMode', true)
            ->has('sessions.data', 22)
            ->has('attendance.data', 21)
            ->where('sessions.meta.total', 22)
            ->where('attendance.meta.total', 21));
});
