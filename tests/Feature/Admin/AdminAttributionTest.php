<?php

use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;
use App\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('admin creating a teacher assigns created_by and logs admin name', function (): void {
    $admin = User::factory()->create([
        'name' => 'المدير أحمد',
        'email' => 'ahmed.admin@almagd.com',
    ]);
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'المعلم محمود',
        'email' => 'mahmoud@almagd.com',
        'phone' => '01011112222',
        'password' => 'SecurePass123!',
        'role' => 'teacher',
    ]);

    $response->assertSessionHasNoErrors();

    $teacher = User::where('email', 'mahmoud@almagd.com')->first();
    expect($teacher)->not->toBeNull()
        ->and($teacher->created_by)->toBe($admin->id)
        ->and($teacher->creator->id)->toBe($admin->id)
        ->and($teacher->creator->name)->toBe('المدير أحمد');

    $event = AuditEvent::where('action', 'admin.user.created')->latest('id')->first();
    expect($event)->not->toBeNull()
        ->and($event->metadata['admin_name'])->toBe('المدير أحمد')
        ->and($event->metadata['user_name'])->toBe('المعلم محمود');
});

test('approving a payment stores reviewed_by and attributes admin name in audit', function (): void {
    $admin = User::factory()->create([
        'name' => 'المسؤولة فاطمة',
        'email' => 'fatima@almagd.com',
    ]);
    $admin->assignRole('admin');

    $student = User::factory()->create(['name' => 'الطالب كريم']);
    $student->assignRole('student');

    $payment = Payment::create([
        'user_id' => $student->id,
        'subscription_id' => null,
        'amount' => 50000,
        'original_amount' => 50000,
        'currency' => 'QAR',
        'gateway' => Payment::GATEWAY_VODAFONE_CASH,
        'status' => Payment::STATUS_PENDING_VERIFICATION,
        'receipt_path' => 'receipts/test.jpg',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.payments.approve', $payment->id), [
        'note' => 'تم التحقق من الحوالة',
    ]);

    $response->assertSessionHasNoErrors();

    $payment->refresh();
    expect($payment->status)->toBe('paid')
        ->and($payment->reviewed_by)->toBe($admin->id)
        ->and($payment->reviewer->name)->toBe('المسؤولة فاطمة');

    $event = AuditEvent::where('action', 'admin.payment.approved')->latest('id')->first();
    expect($event)->not->toBeNull()
        ->and($event->metadata['admin_name'])->toBe('المسؤولة فاطمة');
});

test('audit logs endpoint returns user history and creator details for admins only', function (): void {
    $admin1 = User::factory()->create(['name' => 'أدمن 1']);
    $admin1->assignRole('admin');

    $admin2 = User::factory()->create(['name' => 'أدمن 2']);
    $admin2->assignRole('admin');

    $user = User::factory()->create([
        'name' => 'المعلم عمر',
        'created_by' => $admin1->id,
    ]);
    $user->assignRole('teacher');

    // Simulate an action by admin2
    $this->actingAs($admin2)->patch(route('admin.users.toggle', $user->id));

    // Admin can access audit logs endpoint
    $response = $this->actingAs($admin1)->getJson(route('admin.users.audit-logs', $user->id));
    $response->assertOk()
        ->assertJsonPath('user.name', 'المعلم عمر')
        ->assertJsonPath('user.creator.name', 'أدمن 1')
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'creator', 'created_at'],
            'events',
        ]);

    // Student cannot access audit logs endpoint
    $student = User::factory()->create();
    $student->assignRole('student');
    $this->actingAs($student)->getJson(route('admin.users.audit-logs', $user->id))
        ->assertForbidden();
});
