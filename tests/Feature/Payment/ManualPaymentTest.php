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
use Illuminate\Support\Facades\Cache;
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

    $this->grade = GradeLevel::where('key', 'grade_12_science')->firstOrFail();
    $this->grade->update(['vodafone_cash_number' => '01001234567']);

    $assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'grade_level_id' => $this->grade->id,
        'private_monthly_price' => 90_000,
    ]);

    $group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $assignment->id,
        'monthly_price' => 45_000,
        'capacity' => 2,
    ]);

    $this->subscription = app(SubscriptionService::class)->openForGroup($this->student, $group);
});

function vodafoneCashPayload(array $overrides = []): array
{
    return array_merge([
        'payment_method' => Payment::GATEWAY_VODAFONE_CASH,
        'sender_phone' => '01009876543',
        'receipt' => UploadedFile::fake()->image('transfer-receipt.jpg'),
    ], $overrides);
}

it('only accepts Vodafone Cash and exposes the receiving number for the subscription grade', function (): void {
    expect(app('router')->has('checkout.success'))->toBeFalse()
        ->and(app('router')->has('checkout.cancel'))->toBeFalse()
        ->and(app('router')->has('checkout.mock_gateway'))->toBeFalse()
        ->and(app('router')->has('webhooks.stripe'))->toBeFalse()
        ->and(app('router')->has('webhooks.fatora'))->toBeFalse();

    $this->actingAs($this->student)
        ->get(route('checkout.show', $this->subscription->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Checkout/Index')
            ->where('vodafoneCashNumber', '01001234567')
            ->missing('manualMethods')
        );

    $this->actingAs($this->student)
        ->post(route('checkout.process', $this->subscription->id), [
            'payment_method' => 'gateway',
            'sender_phone' => '01009876543',
            'receipt' => UploadedFile::fake()->image('transfer-receipt.jpg'),
        ])
        ->assertSessionHasErrors('payment_method');

    $this->actingAs($this->student)
        ->post(route('checkout.process', $this->subscription->id), [
            'payment_method' => 'manual',
            'sender_phone' => '01009876543',
            'receipt' => UploadedFile::fake()->image('transfer-receipt.jpg'),
        ])
        ->assertSessionHasErrors('payment_method');

    expect(Payment::count())->toBe(0);
});

it('fails closed for legacy gateway webhooks', function (): void {
    expect(app(FatoraGateway::class)->verifyWebhookSignature('{}', 'invalid'))->toBeFalse()
        ->and(app(TapGateway::class)->verifyWebhookSignature('{}', 'invalid'))->toBeFalse();
});

it('does not expose retired gateway settings or manual payment methods to client pages', function (): void {
    PlatformSetting::updateOrCreate(
        ['key' => 'manual_payment_methods'],
        ['value' => '[{"type":"bank","account_number":"legacy"}]', 'type' => 'string'],
    );
    Cache::forget('platform_settings');

    $this->actingAs($this->student)
        ->get(route('checkout.show', $this->subscription->id))
        ->assertInertia(fn ($page) => $page
            ->missing('settings.active_gateway')
            ->missing('settings.tap_secret_key')
            ->missing('settings.fatora_api_key')
            ->missing('settings.manual_payment_methods')
        );
});

it('stores one Vodafone Cash receipt and keeps the subscription pending until admin approval', function (): void {
    Storage::fake('local');
    Notification::fake();

    $response = $this->actingAs($this->student)
        ->post(route('checkout.process', $this->subscription->id), vodafoneCashPayload());

    $response->assertRedirect(route('student.my-classes'))->assertSessionHasNoErrors();

    $payment = Payment::firstOrFail();

    expect($payment->gateway)->toBe(Payment::GATEWAY_VODAFONE_CASH)
        ->and($payment->gateway_ref)->toBe('Vodafone Cash: 01001234567')
        ->and($payment->sender_phone)->toBe('01009876543')
        ->and($payment->status)->toBe(Payment::STATUS_PENDING_VERIFICATION)
        ->and($payment->receipt_path)->not->toBeNull()
        ->and($this->subscription->fresh()->status)->toBe(Subscription::STATUS_PENDING);

    Storage::disk('local')->assertExists($payment->receipt_path);

    $this->actingAs($this->student)
        ->post(route('checkout.process', $this->subscription->id), vodafoneCashPayload())
        ->assertSessionHas('error');

    expect(Payment::count())->toBe(1);
});

it('accepts a PDF transfer proof and rejects a checkout when the grade has no receiving number', function (): void {
    Storage::fake('local');
    Notification::fake();

    $this->actingAs($this->student)
        ->post(route('checkout.process', $this->subscription->id), vodafoneCashPayload([
            'receipt' => UploadedFile::fake()->createWithContent('vodafone-proof.pdf', "%PDF-1.4\n% Vodafone Cash proof\n"),
        ]))
        ->assertRedirect(route('student.my-classes'))
        ->assertSessionHasNoErrors();

    $payment = Payment::firstOrFail();
    expect($payment->receipt_path)->toEndWith('.pdf');
    Storage::disk('local')->assertExists($payment->receipt_path);

    $receipt = $this->actingAs($this->admin)
        ->get(route('admin.payments.receipt', $payment))
        ->assertOk();
    expect($receipt->headers->get('content-disposition'))->toStartWith('inline;');

    $this->grade->update(['vodafone_cash_number' => null]);

    $this->actingAs($this->student)
        ->post(route('checkout.process', $this->subscription->id), vodafoneCashPayload())
        ->assertSessionHas('error');
});

it('lets an admin set the Vodafone Cash receiving number per grade level', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.grade-levels.update', $this->grade->id), [
            'key' => $this->grade->key,
            'name' => $this->grade->name,
            'name_en' => $this->grade->name_en,
            'stage' => $this->grade->stage,
            'track' => $this->grade->track,
            'is_active' => true,
            'vodafone_cash_number' => '01005556677',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->grade->fresh()->vodafone_cash_number)->toBe('01005556677');
});

it('lets only an admin approve a Vodafone Cash receipt and activate the subscription', function (): void {
    Storage::fake('local');
    Notification::fake();

    $this->actingAs($this->student)
        ->post(route('checkout.process', $this->subscription->id), vodafoneCashPayload());

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

it('lets only an admin reject a Vodafone Cash receipt without activating it', function (): void {
    Storage::fake('local');
    Notification::fake();

    $this->actingAs($this->student)
        ->post(route('checkout.process', $this->subscription->id), vodafoneCashPayload());

    $payment = Payment::firstOrFail();

    $this->actingAs($this->admin)
        ->post(route('admin.payments.reject', $payment), ['reason' => 'الإيصال غير واضح.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_FAILED)
        ->and($payment->fresh()->reviewed_by)->toBe($this->admin->id)
        ->and($this->subscription->fresh()->status)->toBe(Subscription::STATUS_PENDING);
});

it('lets a verified parent submit a Vodafone Cash receipt for the linked student', function (): void {
    Storage::fake('local');
    Notification::fake();

    $parent = User::factory()->create(['email_verified_at' => now()]);
    $parent->assignRole('parent');

    ParentStudentLink::create([
        'parent_user_id' => $parent->id,
        'student_user_id' => $this->student->id,
        'relationship' => 'guardian',
        'verified_at' => now(),
    ]);

    $this->actingAs($parent)
        ->post(route('checkout.process', $this->subscription->id), vodafoneCashPayload([
            'sender_phone' => '01001112223',
        ]))
        ->assertRedirect(route('student.my-classes'))
        ->assertSessionHasNoErrors();

    expect(Payment::firstOrFail()->user_id)->toBe($this->student->id)
        ->and(Payment::firstOrFail()->sender_phone)->toBe('01001112223');
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
