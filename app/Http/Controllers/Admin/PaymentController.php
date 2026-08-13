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
use Illuminate\Support\Facades\Auth;
use App\Services\SecureStoredFileResponse;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = Payment::with(['user:id,name,email', 'subscription.assignment.subject:id,name', 'subscription.assignment.teacher:id,name', 'subscription.group:id,name'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Payments', [
            'payments' => $payments,
            'filters'  => $request->only('status'),
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

        return back()->with('success', 'تم تأكيد الدفع وتفعيل الاشتراك للطالب بنجاح.');
    }

    public function reject(Request $request, Payment $payment)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

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

        return back()->with('success', 'تم رفض إيصال التحويل بنجاح.');
    }

    public function receipt(Payment $payment, SecureStoredFileResponse $files)
    {
        abort_unless($payment->receipt_path, 404);
        if (str_starts_with($payment->receipt_path, '/storage/')) {
            return $files->fromPublicStoragePath($payment->receipt_path);
        }

        return $files->fromLocal($payment->receipt_path);
    }
}

