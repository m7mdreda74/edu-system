<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Application\Payment\Services\PaymentService;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = Payment::with(['user:id,name,email', 'subscription.assignment.subject:id,name', 'subscription.assignment.teacher:id,name', 'subscription.group:id,name'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Payments', [
            'payments' => $payments,
            'filters'  => $request->only('status'),
        ]);
    }

    public function approve(Request $request, Payment $payment, PaymentService $paymentService)
    {
        if ($payment->status !== Payment::STATUS_PENDING_VERIFICATION) {
            return back()->with('error', 'هذه العملية غير معلقة للتحقق.');
        }

        $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $payment->update(['reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'review_notes' => $request->input('note')]);
        $paymentService->completeSuccessfulPayment($payment);

        return back()->with('success', 'تم تأكيد الدفع وتفعيل الاشتراك للطالب بنجاح.');
    }

    public function reject(Request $request, Payment $payment)
    {
        if ($payment->status !== Payment::STATUS_PENDING_VERIFICATION) {
            return back()->with('error', 'هذه العملية غير معلقة للتحقق.');
        }

        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $payment->update([
            'status' => Payment::STATUS_FAILED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $validated['reason'],
        ]);

        return back()->with('success', 'تم رفض إيصال التحويل بنجاح.');
    }

    public function receipt(Payment $payment)
    {
        abort_unless($payment->receipt_path, 404);
        if (str_starts_with($payment->receipt_path, '/storage/')) {
            return Storage::disk('public')->response(substr($payment->receipt_path, 9));
        }

        return Storage::disk('local')->response($payment->receipt_path);
    }
}
