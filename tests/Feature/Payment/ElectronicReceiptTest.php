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
}
