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
use App\Notifications\SubscriptionActivatedNotification;
use App\Domain\Communication\Notifications\PaymentReceivedNotification;
use App\Domain\Communication\Notifications\StudentSubscribedNotification;
use Illuminate\Support\Facades\DB;

/**
 * PaymentService — Application Layer
 *
 * Orchestrates paying for one month of a subscription. Does NOT know about
 * HTTP/Controllers — pure business logic.
 */
class PaymentService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Mark the payment paid, split the commission, invoice it, and switch the
     * subscription on. Idempotent — a repeated admin action is a no-op.
     */
    public function completeSuccessfulPayment(Payment $payment): void
    {
        $completedPayment = DB::transaction(function () use ($payment): ?Payment {
            $payment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->isPaid()) {
                return null;
            }

            if (! $payment->requiresReceiptReview() || $payment->status !== Payment::STATUS_PENDING_VERIFICATION) {
                return null;
            }

            $payment->loadMissing(['subscription.assignment.teacher', 'teacher']);

            // Prefer the immutable teacher/commission snapshot captured when
            // the receipt was submitted. A later assignment edit must not
            // rewrite the economics of an already submitted payment.
            $teacher            = $payment->teacher ?? $payment->subscription?->assignment?->teacher;
            $defaultCommission  = (int) (PlatformSetting::where('key', 'commission_percent')->value('value') ?? 20);
            $commissionPercent  = max(0, min(100, (int) ($payment->commission_percent ?? $teacher?->commission_percent ?? $defaultCommission)));
            $platformCommission = (int) floor(($payment->amount * $commissionPercent) / 100);
            $teacherEarnings    = $payment->amount - $platformCommission;

            if ($platformCommission + $teacherEarnings !== $payment->amount) {
                throw new \LogicException('تعذّر موازنة توزيع الدفعة قبل اعتمادها.');
            }

            $payment->update([
                'status'                     => Payment::STATUS_PAID,
                'paid_at'                    => now(),
                'commission_percent'         => $commissionPercent,
                'platform_commission_amount' => $platformCommission,
                'teacher_earnings'           => $teacherEarnings,
            ]);

            if (! $payment->invoice()->exists()) {
                Invoice::create([
                    'payment_id'     => $payment->id,
                    'invoice_number' => $this->generateInvoiceNumber($payment->id),
                    'issued_at'      => now(),
                ]);
            }

            if ($payment->coupon_id) {
                Coupon::where('id', $payment->coupon_id)->increment('used_count');
            }

            if ($payment->purchase_request_id) {
                \App\Domain\Subscription\Models\PurchaseRequest::where('id', $payment->purchase_request_id)
                    ->update(['status' => \App\Domain\Subscription\Models\PurchaseRequest::STATUS_APPROVED]);
            }

            return $payment;
        });

        if ($completedPayment) {
            $this->activateAndNotify($completedPayment);
        }
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
