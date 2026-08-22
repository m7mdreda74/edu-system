<?php

declare(strict_types=1);

use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('keeps the role filter applied when searching users', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $teacher = User::factory()->create([
        'name' => 'Shared Search Teacher',
        'email' => 'teacher-search@example.com',
    ]);
    $teacher->assignRole('teacher');

    $student = User::factory()->create([
        'name' => 'Shared Search Student',
        'email' => 'student-search@example.com',
    ]);
    $student->assignRole('student');

    $this->actingAs($admin)
        ->get(route('admin.users', ['search' => 'Shared Search', 'role' => 'teacher']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.total', 1)
            ->where('users.data.0.id', $teacher->id));
});
