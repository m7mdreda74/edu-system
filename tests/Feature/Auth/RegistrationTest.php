<?php

namespace Tests\Feature\Auth;

use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $parent = User::factory()->create(['phone' => '50000000']);
        $parent->assignRole('parent');

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '50000001',
            'parent_phone' => $parent->phone,
            'role' => 'student',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $student = User::where('email', 'test@example.com')->firstOrFail();
        $link = ParentStudentLink::where('student_user_id', $student->id)->firstOrFail();

        $this->assertSame($parent->id, $link->parent_user_id);
        $this->assertSame('guardian', $link->relationship);
        $this->assertNotNull($link->verified_at);
    }

    public function test_student_registration_requires_an_existing_parent_account(): void
    {
        $response = $this->post('/register', [
            'name' => 'Unlinked Student',
            'email' => 'unlinked@example.com',
            'phone' => '50000002',
            'parent_phone' => '59999999',
            'role' => 'student',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('parent_phone');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unlinked@example.com']);
    }

    public function test_student_cannot_link_to_a_non_parent_account(): void
    {
        $notParent = User::factory()->create(['phone' => '50000003']);
        $notParent->assignRole('student');

        $response = $this->post('/register', [
            'name' => 'Another Student',
            'email' => 'another@example.com',
            'phone' => '50000004',
            'parent_phone' => $notParent->phone,
            'role' => 'student',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('parent_phone');
        $this->assertDatabaseMissing('users', ['email' => 'another@example.com']);
    }

    public function test_public_registration_cannot_create_a_teacher_account(): void
    {
        $response = $this->post('/register', [
            'name' => 'Unapproved Teacher',
            'email' => 'unapproved-teacher@example.com',
            'phone' => '50000005',
            'role' => 'teacher',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unapproved-teacher@example.com']);
    }
}
