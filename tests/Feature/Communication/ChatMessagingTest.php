<?php

declare(strict_types=1);

namespace Tests\Feature\Communication;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Communication\Models\ChatMessage;
use App\Domain\Communication\Models\Conversation;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->teacher = User::factory()->create(['name' => 'الأستاذ أحمد', 'email_verified_at' => now()]);
    $this->teacher->assignRole('teacher');

    $this->student = User::factory()->create(['name' => 'الطالب محمد', 'email_verified_at' => now()]);
    $this->student->assignRole('student');

    $this->subject = Subject::first() ?? Subject::create(['name' => 'الرياضيات', 'code' => 'MATH']);
    $this->grade = GradeLevel::first() ?? GradeLevel::create(['key' => 'sec_1', 'name' => 'الأول الثانوي', 'stage' => 'secondary', 'order' => 1]);

    $this->assignment = TeachingAssignment::create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => $this->subject->id,
        'grade_level_id' => $this->grade->id,
        'monthly_group_price' => 200,
        'monthly_private_price' => 500,
    ]);

    $this->subscription = Subscription::create([
        'student_id' => $this->student->id,
        'type' => Subscription::TYPE_GROUP,
        'teaching_assignment_id' => $this->assignment->id,
        'monthly_price' => 200,
        'currency' => 'EGP',
        'period_start' => now()->subDay(),
        'period_end' => now()->addMonth(),
        'status' => Subscription::STATUS_ACTIVE,
    ]);
});

it('allows a subscribed student to start a conversation with their teacher', function () {
    $response = $this->actingAs($this->student)
        ->post(route('chat.start'), [
            'teaching_assignment_id' => $this->assignment->id,
            'teacher_id' => $this->teacher->id,
        ]);

    $response->assertRedirect();

    $conversation = Conversation::where('student_id', $this->student->id)
        ->where('teacher_id', $this->teacher->id)
        ->first();

    expect($conversation)->not->toBeNull()
        ->and($conversation->teaching_assignment_id)->toBe($this->assignment->id);
});

it('allows a subscribed student to send plain text messages without attaching a file', function () {
    Notification::fake();

    $conversation = Conversation::create([
        'teaching_assignment_id' => $this->assignment->id,
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'last_message_at' => now(),
    ]);

    // Send a message with text only, without any attachment field
    $response = $this->actingAs($this->student)
        ->from(route('chat.index', ['conversation' => $conversation->id]))
        ->post(route('chat.send'), [
            'conversation_id' => $conversation->id,
            'message' => 'السلام عليكم يا أستاذ، عندي سؤال بخصوص درس المصفوفات',
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('chat.index', ['conversation' => $conversation->id]));

    $this->assertDatabaseHas('chat_messages', [
        'conversation_id' => $conversation->id,
        'sender_id' => $this->student->id,
        'message' => 'السلام عليكم يا أستاذ، عندي سؤال بخصوص درس المصفوفات',
        'attachment_path' => null,
    ]);
});

it('allows sending a message when attachment is explicitly null or empty string', function () {
    Notification::fake();

    $conversation = Conversation::create([
        'teaching_assignment_id' => $this->assignment->id,
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'last_message_at' => now(),
    ]);

    // Test sending with attachment='null' (as can happen with form-data stringification)
    $response = $this->actingAs($this->student)
        ->post(route('chat.send'), [
            'conversation_id' => $conversation->id,
            'message' => 'رسالة نصية بدون ملف مع قيمة null صريحة',
            'attachment' => 'null',
        ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('chat_messages', [
        'conversation_id' => $conversation->id,
        'sender_id' => $this->student->id,
        'message' => 'رسالة نصية بدون ملف مع قيمة null صريحة',
        'attachment_path' => null,
    ]);
});

it('validates that at least message or attachment is provided', function () {
    $conversation = Conversation::create([
        'teaching_assignment_id' => $this->assignment->id,
        'student_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'last_message_at' => now(),
    ]);

    $response = $this->actingAs($this->student)
        ->post(route('chat.send'), [
            'conversation_id' => $conversation->id,
            'message' => '',
            'attachment' => null,
        ]);

    $response->assertSessionHasErrors(['message']);
});
