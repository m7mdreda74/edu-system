<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function lockdownUser(string $role): User
{
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

it('forbids a student from changing their own account data', function (): void {
    $student = lockdownUser('student');
    $originalName = $student->name;

    $this->actingAs($student)
        ->patch(route('profile.update'), ['name' => 'Hacked', 'email' => $student->email])
        ->assertForbidden();

    expect($student->fresh()->name)->toBe($originalName);
});

it('forbids a student from deleting their own account', function (): void {
    $student = lockdownUser('student');

    $this->actingAs($student)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertForbidden();

    expect(User::find($student->id))->not->toBeNull();
});

it('flags students on the profile page so the edit and delete UI is hidden', function (): void {
    $student = lockdownUser('student');

    $this->actingAs($student)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('isStudent', true));
});

it('still lets a student manage notification preferences', function (): void {
    $student = lockdownUser('student');

    $this->actingAs($student)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('notificationPreferences'));
});

it('lets an admin set a student grade', function (): void {
    $admin = lockdownUser('admin');
    $student = lockdownUser('student');
    $grade = GradeLevel::query()->where('is_active', true)->where('key', '!=', 'all')->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('admin.users.grade', ['id' => $student->id]), ['grade_level' => $grade->key])
        ->assertSessionHasNoErrors();

    expect($student->fresh()->grade_level)->toBe($grade->key);
});

it('refuses setting a grade on a non-student and rejects unknown grades', function (): void {
    $admin = lockdownUser('admin');
    $teacher = lockdownUser('teacher');
    $student = lockdownUser('student');
    $grade = GradeLevel::query()->where('is_active', true)->where('key', '!=', 'all')->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('admin.users.grade', ['id' => $teacher->id]), ['grade_level' => $grade->key])
        ->assertStatus(422);

    $this->actingAs($admin)
        ->patch(route('admin.users.grade', ['id' => $student->id]), ['grade_level' => 'not_a_grade'])
        ->assertSessionHasErrors('grade_level');
});

it('forbids a student from setting a grade through the admin endpoint', function (): void {
    $student = lockdownUser('student');
    $grade = GradeLevel::query()->where('is_active', true)->where('key', '!=', 'all')->firstOrFail();

    $this->actingAs($student)
        ->patch(route('admin.users.grade', ['id' => $student->id]), ['grade_level' => $grade->key])
        ->assertForbidden();
});
