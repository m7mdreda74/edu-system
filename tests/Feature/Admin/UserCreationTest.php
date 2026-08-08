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
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $parent = User::factory()->create(['phone' => '51000000']);
        $parent->assignRole('parent');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Admin Created Student',
            'email' => 'admin-created-student@example.com',
            'phone' => '51000001',
            'parent_phone' => $parent->phone,
            'password' => 'password',
            'role' => 'student',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $student = User::where('email', 'admin-created-student@example.com')->firstOrFail();

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
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Unlinked Student',
            'email' => 'admin-unlinked@example.com',
            'phone' => '51000002',
            'parent_phone' => '51999999',
            'password' => 'password',
            'role' => 'student',
        ]);

        $response->assertSessionHasErrors('parent_phone');
        $this->assertDatabaseMissing('users', ['email' => 'admin-unlinked@example.com']);
    }
}
