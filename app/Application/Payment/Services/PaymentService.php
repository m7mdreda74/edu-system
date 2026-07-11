<?php

declare(strict_types=1);

namespace App\Application\Payment\Services;

use App\Domain\Course\Models\Course;
use App\Domain\Enrollment\Contracts\EnrollmentServiceInterface;
use App\Domain\Payment\Models\Coupon;
use App\Domain\Payment\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;
use App\Infrastructure\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use App\Notifications\CourseEnrolledNotification;
use App\Domain\Communication\Notifications\StudentEnrolledNotification;
use App\Domain\Communication\Notifications\PaymentReceivedNotification;

/**
 * PaymentService — Application Layer
 * Orchestrates the full checkout flow.
 * Does NOT know about HTTP/Controllers — pure business logic.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface     $gateway,
        private readonly EnrollmentServiceInterface  $enrollmentService,
    ) {}

    public function getGateway(): PaymentGatewayInterface
    {
        return $this->gateway;
    }

    /**
     * Initiate checkout for a paid course.
     * Returns redirect URL to gateway payment page.
     *
     * @throws LogicException
     */
    public function initiateCheckout(User $user, Course $course, ?string $couponCode = null): array
    {
        // Guard: already enrolled
        if ($user->isEnrolledIn($course)) {
            throw new LogicException('أنت مسجل في هذا الكورس مسبقاً.');
        }

        // Guard: free course should use enrollFree()
        if ($course->isFree()) {
            throw new LogicException('هذا الكورس مجاني. استخدم التسجيل المباشر.');
        }

        // Resolve coupon
        $coupon         = null;
        $originalAmount = $course->getEffectivePrice();
        $finalAmount    = $originalAmount;

        if ($couponCode) {
            /** @var Coupon|null $coupon */
            $coupon = Coupon::where('code', strtoupper(trim($couponCode)))->first();

            if (! $coupon || ! $coupon->isUsable()) {
                throw new LogicException('كود الخصم غير صحيح أو منتهي الصلاحية.');
            }

            $finalAmount = $coupon->applyDiscount($originalAmount);
        }

        // Create pending Payment record first (before gateway call)
        // This enables idempotent webhook processing
        $payment = DB::transaction(function () use ($user, $course, $coupon, $originalAmount, $finalAmount) {
            return Payment::create([
                'user_id'         => $user->id,
                'course_id'       => $course->id,
                'coupon_id'       => $coupon?->id,
                'amount'          => $finalAmount,
                'original_amount' => $originalAmount,
                'currency'        => 'QAR',
                'gateway'         => $this->gateway->getGatewayName(),
                'status'          => Payment::STATUS_PENDING,
            ]);
        });

        // Call gateway to get payment URL
        $gatewayResponse = $this->gateway->createPaymentIntent(
            amountInSmallestUnit: $finalAmount,
            currency: 'QAR',
            metadata: [
                'payment_id' => $payment->id,
                'user_id'    => $user->id,
                'course_id'  => $course->id,
            ]
        );

        // Update payment with gateway reference
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
            return; // Only process successful payments
        }

        $payment = Payment::where('gateway_ref', $event['gateway_ref'])->first();

        if (! $payment) {
            return; // Unknown payment — ignore (log in production)
        }

        $this->completeSuccessfulPayment($payment);
    }

    /**
     * Verify payment status directly with the gateway and process it.
     * Useful for redirect callbacks.
     */
    public function verifyAndProcessPayment(string $paymentId): void
    {
        $gateway = $this->gateway;
        if (method_exists($gateway, 'getPaymentStatus')) {
            /** @var \App\Infrastructure\Payment\Gateways\MyFatoorahGateway $gateway */
            $statusData = $gateway->getPaymentStatus($paymentId);

            if (($statusData['status'] ?? '') === 'paid') {
                $localPaymentId = $statusData['payment_id'] ?? null;
                $payment = null;

                if ($localPaymentId) {
                    $payment = Payment::find($localPaymentId);
                }

                if (! $payment) {
                    $payment = Payment::where('gateway_ref', $statusData['invoice_id'] ?? '')
                        ->orWhere('gateway_ref', $paymentId)
                        ->first();
                }

                if ($payment) {
                    // Update to the final Transaction PaymentId if not set
                    if ($payment->gateway_ref !== $paymentId) {
                        $payment->update(['gateway_ref' => $paymentId]);
                    }
                    $this->completeSuccessfulPayment($payment);
                }
            }
        }
    }

    /**
     * Verify payment status directly with the gateway and process it using local payment ID.
     */
    public function verifyAndProcessPaymentDirect(string $paymentId): void
    {
        $payment = Payment::find($paymentId);
        if ($payment && !$payment->isPaid()) {
            $gatewayRef = $payment->gateway_ref;
            if ($gatewayRef) {
                $gateway = $this->gateway;
                if (method_exists($gateway, 'getPaymentStatus')) {
                    /** @var \App\Infrastructure\Payment\Gateways\FatoraGateway $gateway */
                    $statusData = $gateway->getPaymentStatus($gatewayRef);
                    if (($statusData['status'] ?? '') === 'paid') {
                        $this->completeSuccessfulPayment($payment);
                    }
                }
            }
        }
    }

    /**
     * Complete the payment process and enroll the student.
     */
    public function completeSuccessfulPayment(Payment $payment): void
    {
        // Idempotency check: skip if already processed
        if ($payment->isPaid()) {
            return;
        }

        DB::transaction(function () use ($payment) {
            $payment->update([
                'status'  => Payment::STATUS_PAID,
                'paid_at' => now(),
            ]);

            // Generate invoice
            Invoice::create([
                'payment_id'     => $payment->id,
                'invoice_number' => $this->generateInvoiceNumber($payment->id),
                'issued_at'      => now(),
            ]);

            // Increment coupon usage count
            if ($payment->coupon_id) {
                Coupon::where('id', $payment->coupon_id)->increment('used_count');
            }
        });

        // Enroll student after successful payment
        $course = $payment->course;
        $user   = $payment->user;

        if (! $user->isEnrolledIn($course)) {
            \App\Domain\Enrollment\Models\Enrollment::firstOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id],
                ['enrolled_at' => now(), 'progress_percent' => 0]
            );

            // Eager load teacher
            $course->load('teacher');

            // Notify Student
            $user->notify(new CourseEnrolledNotification($course));

            // Notify Teacher
            if ($course->teacher) {
                $course->teacher->notify(new StudentEnrolledNotification($course, $user));
            }

            // Notify Admins
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new PaymentReceivedNotification($course, $user, $payment->amount));
            }
        }
    }

    private function generateInvoiceNumber(int $paymentId): string
    {
        $year = now()->year;
        $seq  = str_pad((string) $paymentId, 6, '0', STR_PAD_LEFT);
        return "INV-{$year}-{$seq}";
    }
}
