<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use App\Application\Payment\Services\PaymentService;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use App\Services\SecureStoredFileResponse;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,pending_verification,paid,failed,refunded'],
        ]);

        $payments = Payment::with(['user:id,name,email', 'subscription.assignment.subject:id,name', 'subscription.assignment.teacher:id,name', 'subscription.group:id,name'])
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $payments->getCollection()->transform(function (Payment $payment): Payment {
            // Payment review still needs a recognizable transfer identifier,
            // but the full sender number is not required by the table.
            $payment->setAttribute('sender_phone', self::maskPhone($payment->sender_phone));
            $payment->setAttribute('gateway_ref', null);

            return $payment;
        });

        return Inertia::render('Admin/Payments', [
            'payments' => $payments,
            'filters'  => $filters,
        ]);
    }

    public function approve(Request $request, Payment $payment, PaymentService $paymentService)
    {
        $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $approved = DB::transaction(function () use ($payment, $paymentService, $request): bool {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $lockedPayment->requiresReceiptReview() || $lockedPayment->status !== Payment::STATUS_PENDING_VERIFICATION) {
                return false;
            }

            $lockedPayment->update([
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'review_notes' => $request->input('note'),
            ]);

            $paymentService->completeSuccessfulPayment($lockedPayment);

            return true;
        });

        if (! $approved) {
            return back()->with('error', 'هذه العملية غير معلقة للتحقق.');
        }

        AuditLogger::record('admin.payment.approved', $payment->fresh(), [
            'note_hash' => AuditLogger::hashValue($request->input('note')),
        ]);

        return back()->with('success', 'تم تأكيد الدفع وتفعيل الاشتراك للطالب بنجاح.');
    }

    public function reject(Request $request, Payment $payment)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:1', 'max:1000']]);

        $rejected = DB::transaction(function () use ($payment, $validated): bool {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $lockedPayment->requiresReceiptReview() || $lockedPayment->status !== Payment::STATUS_PENDING_VERIFICATION) {
                return false;
            }

            $lockedPayment->update([
                'status' => Payment::STATUS_FAILED,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'review_notes' => $validated['reason'],
            ]);

            return true;
        });

        if (! $rejected) {
            return back()->with('error', 'هذه العملية غير معلقة للتحقق.');
        }

        AuditLogger::record('admin.payment.rejected', $payment->fresh(), [
            'reason_hash' => AuditLogger::hashValue($validated['reason']),
        ]);

        return back()->with('success', 'تم رفض إيصال التحويل بنجاح.');
    }

    public function receipt(Payment $payment, SecureStoredFileResponse $files)
    {
        abort_unless($payment->receipt_path, 404);
        AuditLogger::record('admin.payment.receipt_accessed', $payment);
        if (str_starts_with($payment->receipt_path, '/storage/')) {
            return $files->fromPublicStoragePath($payment->receipt_path);
        }

        return $files->fromLocal($payment->receipt_path);
    }

    private static function maskPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $phone);

        if (! is_string($normalized) || strlen($normalized) <= 4) {
            return '****';
        }

        return str_repeat('*', strlen($normalized) - 4) . substr($normalized, -4);
    }
}

