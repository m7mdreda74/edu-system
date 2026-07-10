<?php

declare(strict_types=1);

use App\Domain\Course\Models\Course;
use App\Domain\User\Models\User;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Admin Access Control Tests ───────────────────────────────────────────────

describe('Admin Role Protection', function () {

    it('blocks students from accessing admin dashboard', function () {
        $student = User::factory()->create();
        $student->assignRole('student');

        $this->actingAs($student)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    });

    it('blocks teachers from accessing admin dashboard', function () {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $this->actingAs($teacher)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    });

    it('allows admins to access dashboard', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    });

    it('prevents admin from deactivating themselves', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)
            ->patch(route('admin.users.toggle', ['id' => $admin->id]));

        $response->assertForbidden();
    });

    it('allows admin to toggle a student active status', function () {
        $admin   = User::factory()->create();
        $admin->assignRole('admin');
        $student = User::factory()->create(['is_active' => true]);
        $student->assignRole('student');

        $this->actingAs($admin)
            ->patch(route('admin.users.toggle', ['id' => $student->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id'        => $student->id,
            'is_active' => false,
        ]);
    });

    it('invalidates courses.featured cache on publish toggle', function () {
        \Illuminate\Support\Facades\Cache::put('courses.featured', collect([]), 1800);

        $admin  = User::factory()->create();
        $admin->assignRole('admin');
        $course = Course::factory()->create(['is_published' => false]);

        $this->actingAs($admin)
            ->patch(route('admin.courses.toggle', ['id' => $course->id]))
            ->assertRedirect();

        // Cache should be cleared
        expect(\Illuminate\Support\Facades\Cache::has('courses.featured'))->toBeFalse();
    });

});

// ─── Teacher Role Protection ──────────────────────────────────────────────────

describe('Teacher Ownership Guard', function () {

    it('prevents a teacher from editing another teacher\'s course', function () {
        $teacher1 = User::factory()->create();
        $teacher1->assignRole('teacher');
        $teacher2 = User::factory()->create();
        $teacher2->assignRole('teacher');

        $course = Course::factory()->create(['teacher_id' => $teacher1->id]);

        // Teacher 2 tries to edit teacher 1's course
        $response = $this->actingAs($teacher2)
            ->get(route('teacher.courses.edit', ['id' => $course->id]));

        $response->assertNotFound(); // ownerCourseOrFail() returns 404
    });

    it('allows teacher to edit their own course', function () {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        $response = $this->actingAs($teacher)
            ->get(route('teacher.courses.edit', ['id' => $course->id]));

        $response->assertOk();
    });

});
