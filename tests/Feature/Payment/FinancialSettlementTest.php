<?php

declare(strict_types=1);

use App\Application\Payment\Services\PaymentService;
use App\Domain\Course\Models\Course;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\TeacherPayout;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Domain\Settings\Models\PlatformSetting;

uses(\Tests\TestCase::class, RefreshDatabase::class);

it('snapshots the teacher commission and enrolls the student after approval', function () {
    $teacher = User::factory()->create(['commission_percent' => 25]);
    $teacher->assignRole('teacher');
    $student = User::factory()->create();
    $student->assignRole('student');
    $course = Course::factory()->create(['teacher_id' => $teacher->id, 'price' => 10_000]);
    $payment = Payment::factory()->create([
        'user_id' => $student->id, 'course_id' => $course->id,
        'amount' => 10_000, 'original_amount' => 10_000,
        'status' => Payment::STATUS_PENDING_VERIFICATION,
    ]);

    app(PaymentService::class)->completeSuccessfulPayment($payment);
    $payment->refresh();

    expect($payment->commission_percent)->toBe(25)
        ->and($payment->platform_commission_amount)->toBe(2_500)
        ->and($payment->teacher_earnings)->toBe(7_500)
        ->and($student->fresh()->isEnrolledIn($course))->toBeTrue();

    $teacher->update(['commission_percent' => 40]);
    expect($payment->fresh()->commission_percent)->toBe(25);
});

it('creates an automatic payout, stores proof, and lets only the teacher acknowledge it', function () {
    Storage::fake('local');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $teacher = User::factory()->create(['commission_percent' => 20]);
    $teacher->assignRole('teacher');
    $student = User::factory()->create();
    $course = Course::factory()->create(['teacher_id' => $teacher->id]);
    $payment = Payment::factory()->paid()->create([
        'user_id' => $student->id, 'course_id' => $course->id,
        'amount' => 10_000, 'commission_percent' => 20,
        'platform_commission_amount' => 2_000, 'teacher_earnings' => 8_000,
    ]);

    $this->actingAs($admin)->post(route('admin.payouts.store'), [
        'teacher_id' => $teacher->id,
        'period_start' => now()->toDateString(),
        'period_end' => now()->toDateString(),
        'notes' => 'تصفية اختبارية',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $payout = TeacherPayout::firstOrFail();
    expect($payout->amount)->toBe(8_000)
        ->and($payout->gross_amount)->toBe(10_000)
        ->and($payment->fresh()->teacher_payout_id)->toBe($payout->id);

    $this->actingAs($admin)->post(route('admin.payouts.pay', $payout->id), [
        'receipt' => UploadedFile::fake()->image('transfer.jpg'),
        'notes' => 'مرجع التحويل 123',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $payout->refresh();
    Storage::disk('local')->assertExists($payout->receipt_path);

    $this->actingAs($teacher)->post(route('teacher.payouts.acknowledge', $payout->id), [
        'note' => 'تم الاستلام',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($payout->fresh()->teacher_acknowledged_at)->not->toBeNull()
        ->and($payout->fresh()->teacher_acknowledgment_note)->toBe('تم الاستلام');
});

it('submits a private wallet receipt for admin review before opening the course', function () {
    Storage::fake('local');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $teacher = User::factory()->create(['commission_percent' => 20]);
    $teacher->assignRole('teacher');
    $student = User::factory()->create();
    $student->assignRole('student');
    $course = Course::factory()->create(['teacher_id' => $teacher->id, 'price' => 12_500, 'is_published' => true]);
    $method = ['type' => 'wallet', 'name' => 'محفظة قطرية', 'account_name' => 'المنصة', 'account_number' => '97450000000', 'instructions' => 'حوّل وارفع الإيصال'];
    PlatformSetting::updateOrCreate(['key' => 'manual_payment_methods'], ['value' => json_encode([$method], JSON_UNESCAPED_UNICODE), 'type' => 'string']);

    $this->actingAs($student)->post(route('checkout.process', $course->slug), [
        'payment_method' => 'manual',
        'selected_method' => json_encode($method, JSON_UNESCAPED_UNICODE),
        'receipt' => UploadedFile::fake()->image('wallet.jpg'),
    ], ['Accept' => 'application/json'])->assertOk()->assertJson(['success' => true]);

    $payment = Payment::latest('id')->firstOrFail();
    expect($payment->status)->toBe(Payment::STATUS_PENDING_VERIFICATION)
        ->and($student->fresh()->isEnrolledIn($course))->toBeFalse();
    Storage::disk('local')->assertExists($payment->receipt_path);
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id]);

    $this->actingAs($admin)->post(route('admin.payments.approve', $payment->id), [
        'note' => 'تمت مراجعة إيصال المحفظة',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($student->fresh()->isEnrolledIn($course))->toBeTrue()
        ->and($payment->fresh()->reviewed_by)->toBe($admin->id);
});
