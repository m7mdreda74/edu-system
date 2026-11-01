<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_created_student_is_linked_to_the_parent_account(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $parent = User::factory()->create(['phone' => '51000000']);
        $parent->assignRole('parent');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Admin Created Student',
            'email' => 'admin-created-student@almagd.com',
            'phone' => '51000001',
            'parent_phone' => $parent->phone,
            'grade_level' => 'grade_10',
            'password' => 'password',
            'role' => 'student',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $student = User::where('email', 'admin-created-student@almagd.com')->firstOrFail();

        $this->assertDatabaseHas('parent_student_links', [
            'parent_user_id' => $parent->id,
            'student_user_id' => $student->id,
            'relationship' => 'guardian',
        ]);
        $this->assertNotNull(
            ParentStudentLink::where('student_user_id', $student->id)->value('verified_at'),
        );
    }

    public function test_admin_cannot_create_an_unlinked_student(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Unlinked Student',
            'email' => 'admin-unlinked@almagd.com',
            'phone' => '51000002',
            'parent_phone' => '51999999',
            'grade_level' => 'grade_10',
            'password' => 'password',
            'role' => 'student',
        ]);

        $response->assertSessionHasErrors('parent_phone');
        $this->assertDatabaseMissing('users', ['email' => 'admin-unlinked@almagd.com']);
    }

    public function test_admin_cannot_create_a_user_with_an_external_email_domain(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'External Email User',
            'email' => 'external@example.com',
            'phone' => '51000003',
            'password' => 'password',
            'role' => 'parent',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'external@example.com']);
    }

    public function test_admin_can_assign_and_edit_multiple_grade_levels_for_a_teacher(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $subject = \App\Domain\Academic\Models\Subject::query()->where('is_active', true)->firstOrFail();
        $grades = \App\Domain\Academic\Models\GradeLevel::query()
            ->where('is_active', true)
            ->where('key', '!=', 'all')
            ->limit(3)
            ->pluck('id')
            ->all();

        $this->assertGreaterThanOrEqual(2, count($grades));

        // 1. Create teacher with initial grade
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Multi Grade Teacher',
            'email' => 'multi-grade-teacher@almagd.com',
            'phone' => '51000010',
            'password' => 'password123',
            'role' => 'teacher',
            'subject_id' => $subject->id,
            'grade_level_ids' => [$grades[0]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $teacher = User::where('email', 'multi-grade-teacher@almagd.com')->firstOrFail();
        $this->assertSame($subject->id, $teacher->subject_id);
        $this->assertCount(1, $teacher->teachingAssignments()->where('is_active', true)->get());

        // 2. Admin adds additional grade levels to the teacher
        $this->actingAs($admin)->patch(route('admin.users.teacher-grades', ['id' => $teacher->id]), [
            'subject_id' => $subject->id,
            'grade_level_ids' => $grades,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $activeGradeIds = $teacher->teachingAssignments()
            ->where('is_active', true)
            ->pluck('grade_level_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $expected = collect($grades)->map(fn ($id) => (int) $id)->sort()->values()->all();
        $this->assertSame($expected, $activeGradeIds);
    }
}
