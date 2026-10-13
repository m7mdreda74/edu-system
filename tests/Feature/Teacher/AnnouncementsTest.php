<?php

declare(strict_types=1);

use App\Application\Communication\Services\GroupAnnouncementService;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Communication\Models\GroupAnnouncement;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('publishes a scoped announcement to active students and verified parents', function (): void {
    Role::findOrCreate('teacher', 'web');
    Role::findOrCreate('student', 'web');
    Role::findOrCreate('parent', 'web');

    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');
    $student = User::factory()->create();
    $student->assignRole('student');
    $verifiedParent = User::factory()->create();
    $verifiedParent->assignRole('parent');
    $pendingParent = User::factory()->create();
    $pendingParent->assignRole('parent');
    $grade = GradeLevel::query()->firstOrFail();
    $subject = Subject::query()->firstOrFail();
    $group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => TeachingAssignment::factory()->create([
            'teacher_id' => $teacher->id,
            'grade_level_id' => $grade->id,
            'subject_id' => $subject->id,
        ])->id,
    ]);

    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_group_id' => $group->id,
        'teaching_assignment_id' => $group->teaching_assignment_id,
    ]);
    ParentStudentLink::create([
        'parent_user_id' => $verifiedParent->id,
        'student_user_id' => $student->id,
        'relationship' => 'father',
        'verified_at' => now(),
        'status' => ParentStudentLink::STATUS_APPROVED,
    ]);
    ParentStudentLink::create([
        'parent_user_id' => $pendingParent->id,
        'student_user_id' => $student->id,
        'relationship' => 'mother',
        'status' => ParentStudentLink::STATUS_PENDING,
    ]);

    Notification::fake();

    $this->actingAs($teacher)
        ->post(route('teacher.announcements.store'), [
            'teaching_group_id' => $group->id,
            'title' => 'مراجعة مهمة',
            'body' => 'موعد المراجعة يوم الأحد.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $announcement = GroupAnnouncement::query()->firstOrFail();
    expect($announcement->teacher_id)->toBe($teacher->id)
        ->and($announcement->teaching_group_id)->toBe($group->id);

    Notification::assertSentTo($student, GenericDatabaseNotification::class);
    Notification::assertSentTo($verifiedParent, GenericDatabaseNotification::class);
    expect(Notification::sent($pendingParent, GenericDatabaseNotification::class))->toHaveCount(0);

    expect(app(GroupAnnouncementService::class)->forStudent($student)->first())
        ->toMatchArray([
            'id' => $announcement->id,
            'title' => 'مراجعة مهمة',
            'group' => ['id' => $group->id, 'name' => $group->name],
        ]);
});

it('rejects a teacher trying to publish to another teachers group', function (): void {
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');
    $otherTeacher = User::factory()->create();
    $otherTeacher->assignRole('teacher');
    $grade = GradeLevel::query()->firstOrFail();
    $subject = Subject::query()->firstOrFail();
    $group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => TeachingAssignment::factory()->create([
            'teacher_id' => $otherTeacher->id,
            'grade_level_id' => $grade->id,
            'subject_id' => $subject->id,
        ])->id,
    ]);

    $this->actingAs($teacher)
        ->post(route('teacher.announcements.store'), [
            'teaching_group_id' => $group->id,
            'title' => 'غير مسموح',
            'body' => 'لا يجب نشر هذا الإعلان.',
        ])
        ->assertNotFound();

    $this->assertDatabaseCount('group_announcements', 0);
});

it('shows only active groups owned by the teacher on the announcements page', function (): void {
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');
    $otherTeacher = User::factory()->create();
    $otherTeacher->assignRole('teacher');
    $grade = GradeLevel::query()->firstOrFail();
    $subject = Subject::query()->firstOrFail();
    $ownedAssignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'grade_level_id' => $grade->id,
        'subject_id' => $subject->id,
    ]);
    $ownedGroup = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $ownedAssignment->id,
    ]);
    $otherAssignment = TeachingAssignment::factory()->create([
        'teacher_id' => $otherTeacher->id,
        'grade_level_id' => $grade->id,
        'subject_id' => $subject->id,
    ]);
    $otherGroup = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $otherAssignment->id,
    ]);

    $this->actingAs($teacher)
        ->get(route('teacher.announcements'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/Announcements')
            ->where('groups', fn ($groups): bool => collect($groups)->pluck('id')->all() === [$ownedGroup->id])
            ->where('groups.0.id', $ownedGroup->id)
            ->missing('groups.1'));
});
