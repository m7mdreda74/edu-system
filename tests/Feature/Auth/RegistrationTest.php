<?php

namespace Tests\Feature\Auth;

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

    public function test_students_can_register_without_a_parent_account(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@altafawwuq.com',
            'phone' => '50000001',
            'role' => 'student',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $student = User::where('email', 'test@altafawwuq.com')->firstOrFail();
        $this->assertTrue($student->hasRole('student'));
        $this->assertDatabaseMissing('parent_student_links', [
            'student_user_id' => $student->id,
        ]);
    }

    public function test_parent_can_create_a_parent_account(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test Parent',
            'email' => 'parent@altafawwuq.com',
            'phone' => '50000002',
            'role' => 'parent',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('parent.dashboard', absolute: false));
        $this->assertTrue(User::where('email', 'parent@altafawwuq.com')->firstOrFail()->hasRole('parent'));
    }

    public function test_public_registration_cannot_create_a_teacher_account(): void
    {
        $response = $this->post('/register', [
            'name' => 'Unapproved Teacher',
            'email' => 'unapproved-teacher@altafawwuq.com',
            'phone' => '50000005',
            'role' => 'teacher',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unapproved-teacher@altafawwuq.com']);
    }

    public function test_registration_rejects_an_email_outside_the_platform_domain(): void
    {
        $response = $this->post('/register', [
            'name' => 'External Email User',
            'email' => 'external@example.com',
            'phone' => '50000006',
            'role' => 'parent',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'external@example.com']);
    }
}
