<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('filters students by group or private enrollment, including students with both', function () {
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create(['email_verified_at' => now()]);
    $teacher->assignRole('teacher');
    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => Subject::query()->firstOrFail()->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
        'is_active' => true,
    ]);
    $group = TeachingGroup::factory()->create(['teaching_assignment_id' => $assignment->id, 'capacity' => 10, 'is_private' => false]);
    $privateGroup = TeachingGroup::factory()->create(['teaching_assignment_id' => $assignment->id, 'capacity' => 1, 'is_private' => false]);
    $privateFlagGroup = TeachingGroup::factory()->create(['teaching_assignment_id' => $assignment->id, 'capacity' => 10, 'is_private' => true]);
    $groupOnly = User::factory()->create();
    $privateOnly = User::factory()->create();
    $both = User::factory()->create();
    $slotOnly = User::factory()->create();
    $subscriptionOnly = User::factory()->create();

    foreach ([[$groupOnly, $group], [$both, $group], [$privateOnly, $privateGroup], [$both, $privateFlagGroup]] as [$student, $bookedGroup]) {
        SessionBooking::create(['student_id' => $student->id, 'teaching_group_id' => $bookedGroup->id, 'status' => 'confirmed']);
    }
    $slot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $assignment->id, 'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(), 'timezone' => 'Asia/Qatar',
        'is_free_intro' => false, 'status' => 'booked',
    ]);
    SessionBooking::create(['student_id' => $slotOnly->id, 'private_session_slot_id' => $slot->id, 'status' => 'confirmed']);
    Subscription::create([
        'student_id' => $subscriptionOnly->id, 'type' => Subscription::TYPE_GROUP,
        'teaching_assignment_id' => $assignment->id, 'teaching_group_id' => $privateGroup->id,
        'monthly_price' => 200, 'currency' => 'EGP', 'period_start' => now()->subDay(),
        'period_end' => now()->addMonth(), 'status' => Subscription::STATUS_ACTIVE,
    ]);

    $idsFor = function (string $type) use ($teacher): array {
        $response = $this->actingAs($teacher)->get(route('teacher.students.index', ['type' => $type]));
        $response->assertOk();
        return collect($response->viewData('page')['props']['students'])->pluck('id')->sort()->values()->all();
    };
    $groupIds = [$groupOnly->id, $both->id];
    $privateIds = [$privateOnly->id, $both->id, $slotOnly->id, $subscriptionOnly->id];
    sort($groupIds);
    sort($privateIds);

    expect($idsFor('group'))->toBe($groupIds)
        ->and($idsFor('private'))->toBe($privateIds);
});
