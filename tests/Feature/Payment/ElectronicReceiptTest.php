<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Domain\Payment\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use App\Infrastructure\Payment\Gateways\SkipCashGateway;
use App\Infrastructure\Payment\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ElectronicReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_skipcash_gateway_by_default(): void
    {
        $gateway = app(PaymentGatewayInterface::class);

        $this->assertInstanceOf(SkipCashGateway::class, $gateway);
        $this->assertSame('skipcash', $gateway->getGatewayName());
    }

    public function test_student_can_view_their_electronic_receipt(): void
    {
        $student = User::factory()->create([
            'phone' => '97455556666',
        ]);

        $payment = Payment::create([
            'user_id'         => $student->id,
            'amount'          => 45000,
            'original_amount' => 45000,
            'currency'        => 'QAR',
            'gateway'         => 'skipcash',
            'gateway_ref'     => 'SKP-TEST-12345',
            'status'          => Payment::STATUS_PAID,
            'paid_at'         => now(),
        ]);

        $response = $this->actingAs($student)->get(route('payments.invoice', $payment->id));

        $response->assertOk();
    }
    public function test_pending_payment_receipt_contains_actual_status_and_is_private(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $payment = Payment::create([
            'user_id' => $student->id,
            'amount' => 45000,
            'original_amount' => 45000,
            'currency' => 'QAR',
            'gateway' => Payment::GATEWAY_VODAFONE_CASH,
            'status' => Payment::STATUS_PENDING_VERIFICATION,
            'paid_at' => null,
        ]);

        $this->actingAs($student)->get(route('payments.invoice', $payment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payment/ReceiptView')
                ->where('payment.status', Payment::STATUS_PENDING_VERIFICATION)
                ->where('payment.is_paid', false));

        $this->actingAs($other)->get(route('payments.invoice', $payment))->assertForbidden();
    }

}
