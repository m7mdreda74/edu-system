<?php

declare(strict_types=1);

use App\Application\Subscription\Services\SubscriptionService;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Payment\Models\Payment;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Settings\Models\PlatformSetting;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use App\Infrastructure\Payment\Gateways\FatoraGateway;
use App\Infrastructure\Payment\Gateways\TapGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->teacher = User::factory()->create(['is_active' => true]);
    $this->teacher->assignRole('teacher');

    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->student->assignRole('student');

    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'grade_level_id' => GradeLevel::where('key', 'grade_12_science')->firstOrFail()->id,
        'private_monthly_price' => 90_000,
    ]);

    $group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'monthly_price' => 45_000,
        'capacity' => 2,
    ]);

    $this->subscription = app(SubscriptionService::class)->openForGroup($this->student, $group);

    PlatformSetting::updateOrCreate(
        ['key' => 'manual_payment_methods'],
        [
            'value' => json_encode([
                [
                    'type' => 'bank',
                    'name' => 'حساب المنصة',
                    'account_name' => 'منصة التفوق',
                    'account_number' => 'QA00 0000 0000 0000',
                    'instructions' => 'اكتب رقم الاشتراك في وصف التحويل.',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'type' => 'string',
        ],
    );
});

function manualPaymentPayload(): array
{
    return [
        'payment_method' => 'manual',
        'selected_method' => json_encode([
            'type' => 'bank',
            'name' => 'حساب المنصة',
            'account_number' => 'QA00 0000 0000 0000',
        ], JSON_UNESCAPED_UNICODE),
        'receipt' => UploadedFile::fake()->image('transfer-receipt.jpg'),
    ];
}

it('removes online checkout routes and rejects an online payment payload', function (): void {
    expect(app('router')->has('checkout.success'))->toBeFalse()
        ->and(app('router')->has('checkout.cancel'))->toBeFalse()
        ->and(app('router')->has('checkout.mock_gateway'))->toBeFalse()
        ->and(app('router')->has('webhooks.stripe'))->toBeFalse()
        ->and(app('router')->has('webhooks.fatora'))->toBeFalse();

    $this->actingAs($this->student)
        ->post(route('checkout.process', $this->subscription->id), [
            'payment_method' => 'gateway',
            'receipt' => UploadedFile::fake()->image('transfer-receipt.jpg'),
        ])
        ->assertSessionHasErrors('payment_method');

    expect(Payment::count())->toBe(0);
});

it('fails closed for legacy gateway webhooks', function (): void {
    expect(app(FatoraGateway::class)->verifyWebhookSignature('{}', 'invalid'))->toBeFalse()
        ->and(app(TapGateway::class)->verifyWebhookSignature('{}', 'invalid'))->toBeFalse();
});

it('does not expose retired gateway settings to client pages', function (): void {
    $settings = PlatformSetting::getAllCached();

    expect(array_key_exists('active_gateway', $settings))->toBeFalse()
        ->and(array_key_exists('tap_secret_key', $settings))->toBeFalse()
        ->and(array_key_exists('fatora_api_key', $settings))->toBeFalse();
});

it('stores one manual receipt and keeps the subscription pending until admin approval', function (): void {
    Storage::fake('local');
    Notification::fake();

    $response = $this->actingAs($this->student)
        ->post(route('checkout.process', $this->subscription->id), manualPaymentPayload());

    $response->assertRedirect(route('student.my-classes'))->assertSessionHasNoErrors();

    $payment = Payment::firstOrFail();

    expect($payment->gateway)->toBe('manual')
        ->and($payment->status)->toBe(Payment::STATUS_PENDING_VERIFICATION)
        ->and($payment->receipt_path)->not->toBeNull()
        ->and($this->subscription->fresh()->status)->toBe(Subscription::STATUS_PENDING);

    Storage::disk('local')->assertExists($payment->receipt_path);

    $this->actingAs($this->student)
        ->post(route('checkout.process', $this->subscription->id), manualPaymentPayload())
        ->assertSessionHas('error');

    expect(Payment::count())->toBe(1);
});

it('lets only an admin approve a manual receipt and activate the subscription', function (): void {
    Storage::fake('local');
    Notification::fake();

    $this->actingAs($this->student)
        ->post(route('checkout.process', $this->subscription->id), manualPaymentPayload());

    $payment = Payment::firstOrFail();

    $this->actingAs($this->teacher)
        ->post(route('admin.payments.approve', $payment))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->post(route('admin.payments.approve', $payment), ['note' => 'تمت مطابقة التحويل.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_PAID)
        ->and($payment->fresh()->reviewed_by)->toBe($this->admin->id)
        ->and($this->subscription->fresh()->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($payment->fresh()->invoice)->not->toBeNull();
});

it('lets only an admin reject a manual receipt without activating it', function (): void {
    Storage::fake('local');
    Notification::fake();

    $this->actingAs($this->student)
        ->post(route('checkout.process', $this->subscription->id), manualPaymentPayload());

    $payment = Payment::firstOrFail();

    $this->actingAs($this->admin)
        ->post(route('admin.payments.reject', $payment), ['reason' => 'الإيصال غير واضح.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_FAILED)
        ->and($payment->fresh()->reviewed_by)->toBe($this->admin->id)
        ->and($this->subscription->fresh()->status)->toBe(Subscription::STATUS_PENDING);
});

it('does not let an unverified parent pay for a student', function (): void {
    $parent = User::factory()->create(['email_verified_at' => now()]);
    $parent->assignRole('parent');

    ParentStudentLink::create([
        'parent_user_id' => $parent->id,
        'student_user_id' => $this->student->id,
        'relationship' => 'guardian',
        'verified_at' => null,
    ]);

    $this->actingAs($parent)
        ->get(route('checkout.show', $this->subscription->id))
        ->assertForbidden();
});
