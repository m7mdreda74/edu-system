<?php

declare(strict_types=1);

namespace App\Infrastructure\Observers;

use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentAuditLog;
use Illuminate\Support\Facades\Cache;

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
    public function created(Payment $payment): void
    {
        PaymentAuditLog::create([
            'payment_id' => $payment->id,
            'status'     => $payment->status,
            'ip_address' => request()->ip(),
            'payload'    => [
                'event' => 'created',
                'gateway' => $payment->gateway,
                'amount' => $payment->amount,
            ],
        ]);
    }

    public function updated(Payment $payment): void
    {
        if ($payment->wasChanged('status')) {
            PaymentAuditLog::create([
                'payment_id' => $payment->id,
                'status'     => $payment->status,
                'ip_address' => request()->ip(),
                'payload'    => [
                    'event' => 'status_changed',
                    'original' => $payment->getOriginal('status'),
                    'new' => $payment->status,
                ],
            ]);

            $teacherId = $payment->course()->value('teacher_id');
            if ($teacherId) {
                Cache::forget("teacher_stats:{$teacherId}");
            }
            Cache::forget('admin_platform_stats');
        }
    }
}
