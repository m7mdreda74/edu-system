<?php

declare(strict_types=1);

namespace App\Application\Payment\Services;

use App\Application\Subscription\Services\SubscriptionService;
use App\Domain\Payment\Models\Coupon;
use App\Domain\Payment\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Domain\Settings\Models\PlatformSetting;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use App\Infrastructure\Payment\PaymentGatewayInterface;
use App\Notifications\SubscriptionActivatedNotification;
use App\Domain\Communication\Notifications\PaymentReceivedNotification;
use App\Domain\Communication\Notifications\StudentSubscribedNotification;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * PaymentService — Application Layer
 *
 * Orchestrates paying for one month of a subscription. Does NOT know about
 * HTTP/Controllers — pure business logic.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly SubscriptionService     $subscriptions,
    ) {}

    public function getGateway(): PaymentGatewayInterface
    {
        return $this->gateway;
    }

    /**
     * Start checkout for a pending subscription.
     * Returns the gateway URL the student should be sent to.
     *
     * @return array{payment_id: int, redirect_url: string}
     *
     * @throws LogicException
     */
    public function initiateCheckout(User $user, Subscription $subscription, ?string $couponCode = null): array
    {
        if ($subscription->status === Subscription::STATUS_ACTIVE) {
            throw new LogicException('هذا الاشتراك مفعّل بالفعل.');
        }

        if ($subscription->monthly_price <= 0) {
            throw new LogicException('هذا الاشتراك مجاني — لا يحتاج إلى دفع.');
        }

        [$coupon, $originalAmount, $finalAmount] = $this->resolveAmount($subscription, $couponCode);

        // Create the pending Payment before calling the gateway so the webhook
        // has a record to match against — this is what makes it idempotent.
        $payment = DB::transaction(fn () => Payment::create([
            'user_id'         => $user->id,
            'subscription_id' => $subscription->id,
            'coupon_id'       => $coupon?->id,
            'amount'          => $finalAmount,
            'original_amount' => $originalAmount,
            'currency'        => $subscription->currency ?? 'QAR',
            'gateway'         => $this->gateway->getGatewayName(),
            'status'          => Payment::STATUS_PENDING,
        ]));

        $gatewayResponse = $this->gateway->createPaymentIntent(
            amountInSmallestUnit: $finalAmount,
            currency: $subscription->currency ?? 'QAR',
            metadata: [
                'payment_id'      => $payment->id,
                'user_id'         => $user->id,
                'subscription_id' => $subscription->id,
            ],
        );

        $payment->update(['gateway_ref' => $gatewayResponse['gateway_ref']]);

        return [
            'payment_id'   => $payment->id,
            'redirect_url' => $gatewayResponse['redirect_url'],
        ];
    }

    /**
     * Process a verified webhook event — MUST be idempotent.
     * Called by WebhookController after signature verification.
     */
    public function processWebhookEvent(string $payload): void
    {
        $event = $this->gateway->parseWebhookEvent($payload);

        if ($event['status'] !== 'paid') {
            return; // Only successful payments matter here.
        }

        $payment = Payment::where('gateway_ref', $event['gateway_ref'])->first();

        if (! $payment) {
            return; // Unknown payment — nothing to reconcile.
        }

        $this->completeSuccessfulPayment($payment);
    }

    /**
     * Verify a payment straight with the gateway, then process it.
     * Used by redirect callbacks where no webhook has arrived yet.
     */
    public function verifyAndProcessPayment(string $gatewayReference): void
    {
        $gateway = $this->gateway;

        if (! method_exists($gateway, 'getPaymentStatus')) {
            return;
        }

        $statusData = $gateway->getPaymentStatus($gatewayReference);

        if (($statusData['status'] ?? '') !== 'paid') {
            return;
        }

        $payment = null;

        if (! empty($statusData['payment_id'])) {
            $payment = Payment::find($statusData['payment_id']);
        }

        $payment ??= Payment::where('gateway_ref', $statusData['invoice_id'] ?? '')
            ->orWhere('gateway_ref', $gatewayReference)
            ->first();

        if (! $payment) {
            return;
        }

        if ($payment->gateway_ref !== $gatewayReference) {
            $payment->update(['gateway_ref' => $gatewayReference]);
        }

        $this->completeSuccessfulPayment($payment);
    }

    /**
     * Mark the payment paid, split the commission, invoice it, and switch the
     * subscription on. Idempotent — a repeated webhook is a no-op.
     */
    public function completeSuccessfulPayment(Payment $payment): void
    {
        if ($payment->isPaid()) {
            return;
        }

        DB::transaction(function () use ($payment): void {
            $payment->loadMissing('subscription.assignment.teacher');

            $teacher            = $payment->subscription?->assignment?->teacher;
            $defaultCommission  = (int) (PlatformSetting::where('key', 'commission_percent')->value('value') ?? 20);
            $commissionPercent  = max(0, min(100, $teacher?->commission_percent ?? $defaultCommission));
            $platformCommission = (int) floor(($payment->amount * $commissionPercent) / 100);

            $payment->update([
                'status'                     => Payment::STATUS_PAID,
                'paid_at'                    => now(),
                'commission_percent'         => $commissionPercent,
                'platform_commission_amount' => $platformCommission,
                'teacher_earnings'           => $payment->amount - $platformCommission,
            ]);

            Invoice::create([
                'payment_id'     => $payment->id,
                'invoice_number' => $this->generateInvoiceNumber($payment->id),
                'issued_at'      => now(),
            ]);

            if ($payment->coupon_id) {
                Coupon::where('id', $payment->coupon_id)->increment('used_count');
            }

            if ($payment->purchase_request_id) {
                \App\Domain\Subscription\Models\PurchaseRequest::where('id', $payment->purchase_request_id)
                    ->update(['status' => \App\Domain\Subscription\Models\PurchaseRequest::STATUS_APPROVED]);
            }
        });

        $this->activateAndNotify($payment);
    }

    /**
     * Coupon resolution and the amount actually charged.
     *
     * @return array{0: ?Coupon, 1: int, 2: int}
     *
     * @throws LogicException
     */
    private function resolveAmount(Subscription $subscription, ?string $couponCode): array
    {
        $originalAmount = $subscription->monthly_price;

        if (! $couponCode) {
            return [null, $originalAmount, $originalAmount];
        }

        /** @var Coupon|null $coupon */
        $coupon = Coupon::where('code', strtoupper(trim($couponCode)))->first();

        if (! $coupon || ! $coupon->isUsable()) {
            throw new LogicException('كود الخصم غير صحيح أو منتهي الصلاحية.');
        }

        return [$coupon, $originalAmount, $coupon->applyDiscount($originalAmount)];
    }

    /** Switch the subscription on and let everyone who cares know. */
    private function activateAndNotify(Payment $payment): void
    {
        $subscription = $payment->subscription;

        if (! $subscription) {
            return;
        }

        $wasAlreadyActive = $subscription->status === Subscription::STATUS_ACTIVE;

        $this->subscriptions->activate($subscription);

        if ($wasAlreadyActive) {
            return; // Renewal of a live subscription — no need to re-announce.
        }

        $subscription->loadMissing(['student', 'assignment.teacher']);
        $student = $subscription->student;

        $student?->notify(new SubscriptionActivatedNotification($subscription));

        $subscription->assignment?->teacher?->notify(
            new StudentSubscribedNotification($subscription, $student)
        );

        foreach (User::role('admin')->get() as $admin) {
            $admin->notify(new PaymentReceivedNotification($subscription, $student, $payment->amount));
        }
    }

    private function generateInvoiceNumber(int $paymentId): string
    {
        $year = now()->year;
        $seq  = str_pad((string) $paymentId, 6, '0', STR_PAD_LEFT);

        return "INV-{$year}-{$seq}";
    }
}
