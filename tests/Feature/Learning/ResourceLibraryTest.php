<?php

declare(strict_types=1);

use App\Application\Learning\Services\ResourceLibraryService;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Learning\Models\LearningResource;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('services.vercel_blob.serverless', false);
});

function resourceTeacher(string $name = 'Resource Teacher'): User
{
    $teacher = User::factory()->create([
        'name' => $name,
        'email_verified_at' => now(),
    ]);
    $teacher->assignRole('teacher');

    return $teacher;
}

function resourceAssignment(User $teacher): TeachingAssignment
{
    return TeachingAssignment::factory()->create([
        'teacher_id' => $teacher->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);
}

it('uploads searchable teacher-owned resources with normalized tags', function (): void {
    $teacher = resourceTeacher();
    resourceAssignment($teacher);

    $this->actingAs($teacher)
        ->post(route('teacher.resource-library.store'), [
            'title' => 'ورقة مراجعة الجبر',
            'description' => 'تدريب قبل الاختبار النهائي.',
            'category' => 'مراجعة',
            'tags' => "جبر، تدريب\nنهائي، جبر",
            'attachment' => UploadedFile::fake()->create('algebra.pdf', 24, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $resource = LearningResource::query()->latest('id')->firstOrFail();

    expect($resource->owner_teacher_id)->toBe($teacher->id)
        ->and($resource->tags)->toBe(['جبر', 'تدريب', 'نهائي'])
        ->and($resource->is_active)->toBeTrue()
        ->and($resource->file_path)->toStartWith('private://');

    Storage::disk('local')->assertExists(substr($resource->file_path, strlen('private://')));

    $this->actingAs($teacher)
        ->get(route('teacher.resource-library', ['search' => 'الجبر']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/ResourceLibrary')
            ->where('resources.data.0.id', $resource->id)
            ->where('resources.data.0.tags.0', 'جبر'));
});

it('authorizes and completes a direct Blob resource upload', function (): void {
    $teacher = resourceTeacher();
    config()->set([
        'services.vercel_blob.enabled' => true,
        'services.vercel_blob.store_id' => 'resource-store',
        'services.vercel_blob.serverless' => true,
    ]);

    $authorization = $this->actingAs($teacher)
        ->postJson(route('teacher.resource-library.authorize-upload'), [
            'title' => 'ملف إثرائي',
            'description' => 'ملف مباشر من التخزين الدائم.',
            'category' => 'إثراء',
            'tags' => 'إضافي، مراجعة',
            'file_name' => 'enrichment.pdf',
            'file_size' => 2048,
            'mime_type' => 'application/pdf',
        ])
        ->assertOk()
        ->json();

    expect($authorization['resource_id'])->toBeInt()
        ->and($authorization['pathname'])->toStartWith("curriculum/{$teacher->id}/resource/")
        ->and($authorization['max_bytes'])->toBe(25 * 1024 * 1024);

    $this->actingAs($teacher)
        ->post(route('teacher.resource-library.complete-upload', $authorization['resource_id']), [
            'blob_url' => 'https://resource-store.private.blob.vercel-storage.com/'.$authorization['pathname'],
            'blob_pathname' => $authorization['pathname'],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $resource = LearningResource::query()->findOrFail($authorization['resource_id']);

    expect($resource->is_active)->toBeTrue()
        ->and($resource->file_path)->toBe('https://resource-store.private.blob.vercel-storage.com/'.$authorization['pathname']);
});

it('requires admin approval before another teacher can reuse a shared resource', function (): void {
    $owner = resourceTeacher('Owner');
    $otherTeacher = resourceTeacher('Other Teacher');
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $ownerAssignment = resourceAssignment($owner);
    $otherAssignment = resourceAssignment($otherTeacher);
    $resource = LearningResource::factory()->create(['owner_teacher_id' => $owner->id]);

    $this->actingAs($owner)
        ->post(route('teacher.resource-library.request-share', $resource))
        ->assertRedirect();

    expect($resource->fresh()->approval_status)->toBe(LearningResource::APPROVAL_PENDING)
        ->and(app(ResourceLibraryService::class)->teacherIndex($otherTeacher)['resources']->getCollection())
        ->toHaveCount(0);

    $this->actingAs($admin)
        ->patch(route('admin.resource-library.approve', $resource))
        ->assertRedirect();

    $this->actingAs($otherTeacher)
        ->post(route('teacher.resource-library.attach', $resource), [
            'assignment_id' => $otherAssignment->id,
        ])
        ->assertRedirect();

    expect($resource->fresh()->isApprovedShared())->toBeTrue()
        ->and($resource->assignments()->pluck('teaching_assignments.id')->all())
        ->toContain($otherAssignment->id)
        ->and($resource->assignments()->pluck('teaching_assignments.id')->all())
        ->not->toContain($ownerAssignment->id);
});

it('allows only eligible subscribed students and verified parents to download attached resources', function (): void {
    $teacher = resourceTeacher();
    $assignment = resourceAssignment($teacher);
    $student = User::factory()->create(['email_verified_at' => now()]);
    $student->assignRole('student');
    $foreignStudent = User::factory()->create(['email_verified_at' => now()]);
    $foreignStudent->assignRole('student');
    $parent = User::factory()->create(['email_verified_at' => now()]);
    $parent->assignRole('parent');
    $resource = LearningResource::factory()->create([
        'owner_teacher_id' => $teacher->id,
        'file_path' => 'private://resources/geometry.pdf',
    ]);
    $resource->assignments()->attach($assignment->id, ['attached_by_teacher_id' => $teacher->id]);
    Storage::disk('local')->put('resources/geometry.pdf', 'resource contents');

    Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_assignment_id' => $assignment->id,
        'teaching_group_id' => null,
        'type' => Subscription::TYPE_PRIVATE,
        'period_end' => today()->addMonth(),
    ]);
    ParentStudentLink::create([
        'parent_user_id' => $parent->id,
        'student_user_id' => $student->id,
        'relationship' => 'guardian',
        'status' => ParentStudentLink::STATUS_APPROVED,
        'verified_at' => now(),
    ]);

    $this->actingAs($student)
        ->get(route('student.resources'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Student/Resources')
            ->where('resources.0.id', $resource->id));

    $this->actingAs($student)
        ->get(route('learning.resource.download', $resource))
        ->assertOk()
        ->assertDownload('geometry.pdf');

    $this->actingAs($parent)
        ->get(route('learning.resource.download', $resource))
        ->assertOk();

    $this->actingAs($foreignStudent)
        ->get(route('learning.resource.download', $resource))
        ->assertForbidden();
});
