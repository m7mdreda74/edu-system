<?php

declare(strict_types=1);

namespace App\Infrastructure\Observers;

use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentAuditLog;
use Illuminate\Support\Facades\Cache;

/**
 * Reacts to Payment model events — writes the audit trail and busts the stats
 * caches. Activating the subscription itself stays in PaymentService, which
 * guards it for idempotency; doing it here would fire twice on a retried
 * webhook.
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

            $payment->loadMissing('subscription.assignment');
            $teacherId = $payment->subscription?->assignment?->teacher_id;

            if ($teacherId) {
                Cache::forget("teacher_stats:{$teacherId}");
            }
            Cache::forget('admin_platform_stats');
        }
    }
}
