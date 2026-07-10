<?php

declare(strict_types=1);

namespace App\Infrastructure\Observers;

use App\Domain\Payment\Models\Payment;

/**
 * Reacts to Payment model events.
 * When a payment is marked as "paid", it triggers enrollment and invoice generation.
 * This separates side-effects from core business logic (SRP, Observer Pattern).
 *
 * NOTE: The actual enrollment happens via a Queue Job to ensure idempotency.
 * We do NOT enroll directly here to avoid duplicate enrollments if the observer fires twice.
 */
class PaymentObserver
{
    public function updated(Payment $payment): void
    {
        // Only react when transitioning TO "paid" status — not on every update
        if (
            $payment->wasChanged('status')
            && $payment->isPaid()
        ) {
            // Dispatch via Queue — ensures idempotent processing (ShouldBeUnique)
            // \App\Jobs\ProcessPaymentPaid::dispatch($payment);  // Phase 4
        }
    }
}
