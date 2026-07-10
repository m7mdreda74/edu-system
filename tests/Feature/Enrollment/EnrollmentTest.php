<?php

declare(strict_types=1);

use App\Domain\Course\Models\Course;
use App\Domain\User\Models\User;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Enrollment Feature Tests ─────────────────────────────────────────────────

describe('Free Course Enrollment', function () {

    it('allows a student to enroll in a free course', function () {
        $student = User::factory()->create();
        $student->assignRole('student');

        $course = Course::factory()->create(['price' => 0, 'is_published' => true]);

        $response = $this->actingAs($student)
            ->post(route('student.enroll', ['slug' => $course->slug]));

        $response->assertRedirectToRoute('student.learn', ['slug' => $course->slug]);

        $this->assertDatabaseHas('enrollments', [
            'user_id'   => $student->id,
            'course_id' => $course->id,
        ]);
    });

    it('is idempotent — double enroll returns same enrollment', function () {
        $student = User::factory()->create();
        $student->assignRole('student');

        $course = Course::factory()->create(['price' => 0, 'is_published' => true]);

        // Enroll twice
        $this->actingAs($student)->post(route('student.enroll', ['slug' => $course->slug]));
        $this->actingAs($student)->post(route('student.enroll', ['slug' => $course->slug]));

        // Only 1 enrollment record should exist
        $this->assertDatabaseCount('enrollments', 1);
    });

    it('rejects guest users from enrolling', function () {
        $course = Course::factory()->create(['price' => 0, 'is_published' => true]);

        $response = $this->post(route('student.enroll', ['slug' => $course->slug]));

        $response->assertRedirectToRoute('login');
        $this->assertDatabaseCount('enrollments', 0);
    });

    it('rejects a teacher role from using student enroll route', function () {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $course = Course::factory()->create(['price' => 0, 'is_published', true]);

        $response = $this->actingAs($teacher)
            ->post(route('student.enroll', ['slug' => $course->slug]));

        // Role middleware blocks teachers from student routes
        $response->assertForbidden();
    });

});

describe('Paid Course Enrollment Guard', function () {

    it('rejects direct free-enroll for paid courses', function () {
        $student = User::factory()->create();
        $student->assignRole('student');

        $course = Course::factory()->create([
            'price'        => 50_000, // 500 QAR in halala
            'is_published' => true,
        ]);

        // EnrollService.enrollFree() should throw LogicException
        // The EnrollController catches it and flashes error
        $response = $this->actingAs($student)
            ->post(route('student.enroll', ['slug' => $course->slug]));

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('enrollments', 0);
    });

});
