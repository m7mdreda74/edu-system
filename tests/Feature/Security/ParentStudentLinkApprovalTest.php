<?php

declare(strict_types=1);

use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    foreach (['student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->parent = User::factory()->create(['phone' => '50000001']);
    $this->parent->assignRole('parent');

    $this->student = User::factory()->create([
        'phone' => '50000002',
        'email_verified_at' => now(),
    ]);
    $this->student->assignRole('student');

    $this->otherStudent = User::factory()->create([
        'phone' => '50000003',
        'email_verified_at' => now(),
    ]);
    $this->otherStudent->assignRole('student');
});

it('keeps a phone-based parent request pending until the student approves it', function (): void {
    Notification::fake();

    $this->actingAs($this->parent)
        ->post(route('parent.link-student'), [
            'student_phone' => $this->student->phone,
            'relationship' => 'father',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $link = ParentStudentLink::firstOrFail();

    expect($link->status)->toBe(ParentStudentLink::STATUS_PENDING)
        ->and($link->verified_at)->toBeNull()
        ->and($link->expires_at)->not->toBeNull();

    Notification::assertSentTo($this->student, GenericDatabaseNotification::class);

    $this->actingAs($this->parent)
        ->get(route('parent.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('selectedStudent', null)
            ->has('pendingLinks', 1));
});

it('prevents an unrelated student from approving another students request', function (): void {
    $this->actingAs($this->parent)->post(route('parent.link-student'), [
        'student_phone' => $this->student->phone,
        'relationship' => 'guardian',
    ]);

    $link = ParentStudentLink::firstOrFail();

    $this->actingAs($this->otherStudent)
        ->post(route('student.parent-links.approve', ['linkId' => $link->id]))
        ->assertNotFound();

    expect($link->fresh()->verified_at)->toBeNull();
});

it('lets only the target student approve and then exposes the parent dashboard', function (): void {
    Notification::fake();

    $this->actingAs($this->parent)->post(route('parent.link-student'), [
        'student_phone' => $this->student->phone,
        'relationship' => 'mother',
    ]);
    $link = ParentStudentLink::firstOrFail();

    $this->actingAs($this->student)
        ->post(route('student.parent-links.approve', ['linkId' => $link->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($link->fresh()->effectiveStatus())->toBe(ParentStudentLink::STATUS_APPROVED)
        ->and($this->parent->fresh()->children()->pluck('users.id')->all())->toContain($this->student->id);

    Notification::assertSentTo($this->parent, GenericDatabaseNotification::class);

    $this->actingAs($this->parent)
        ->get(route('parent.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('selectedStudent.student.id', $this->student->id)
            ->has('links', 1));
});

it('does not grant access when the student rejects or the request expires', function (): void {
    $this->actingAs($this->parent)->post(route('parent.link-student'), [
        'student_phone' => $this->student->phone,
        'relationship' => 'guardian',
    ]);
    $link = ParentStudentLink::firstOrFail();

    $this->actingAs($this->student)
        ->post(route('student.parent-links.reject', ['linkId' => $link->id]))
        ->assertRedirect();

    expect($link->fresh()->status)->toBe(ParentStudentLink::STATUS_REJECTED)
        ->and($this->parent->fresh()->children()->exists())->toBeFalse();

    $this->actingAs($this->parent)->post(route('parent.link-student'), [
        'student_phone' => $this->student->phone,
        'relationship' => 'guardian',
    ]);
    $replacement = $link->fresh();
    $replacement->update(['expires_at' => now()->subMinute()]);

    $this->actingAs($this->student)
        ->post(route('student.parent-links.approve', ['linkId' => $replacement->id]))
        ->assertSessionHasErrors('link');

    expect($replacement->fresh()->status)->toBe(ParentStudentLink::STATUS_EXPIRED)
        ->and($replacement->fresh()->verified_at)->toBeNull();
});

it('uses a generic response for an unknown phone and throttles repeated attempts', function (): void {
    $response = $this->actingAs($this->parent)
        ->post(route('parent.link-student'), [
            'student_phone' => '59999999',
            'relationship' => 'guardian',
        ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    expect(ParentStudentLink::count())->toBe(0);

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($this->parent)->post(route('parent.link-student'), [
            'student_phone' => '59999999',
            'relationship' => 'guardian',
        ]);
    }

    $this->actingAs($this->parent)
        ->post(route('parent.link-student'), [
            'student_phone' => $this->otherStudent->phone,
            'relationship' => 'guardian',
        ])
        ->assertSessionHasErrors('student_phone');
});

it('does not duplicate a still-pending request or its notification on retry', function (): void {
    Notification::fake();

    foreach (range(1, 2) as $attempt) {
        $this->actingAs($this->parent)
            ->post(route('parent.link-student'), [
                'student_phone' => $this->student->phone,
                'relationship' => 'guardian',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    expect(ParentStudentLink::count())->toBe(1)
        ->and(Notification::sent($this->student, GenericDatabaseNotification::class))->toHaveCount(1);
});

it('revokes an approved link without deleting its audit history', function (): void {
    $link = ParentStudentLink::create([
        'parent_user_id' => $this->parent->id,
        'student_user_id' => $this->student->id,
        'relationship' => 'guardian',
        'status' => ParentStudentLink::STATUS_APPROVED,
        'verified_at' => now(),
    ]);

    $this->actingAs($this->parent)
        ->delete(route('parent.unlink-student', ['id' => $link->id]))
        ->assertRedirect();

    expect($link->fresh()->status)->toBe(ParentStudentLink::STATUS_REVOKED)
        ->and($link->fresh()->verified_at)->toBeNull()
        ->and($this->parent->fresh()->children()->exists())->toBeFalse();
});
