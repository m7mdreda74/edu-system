<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Application\Payment\Services\PaymentService;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = Payment::with(['user:id,name,email', 'course:id,title'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Payments', [
            'payments' => $payments,
            'filters'  => $request->only('status'),
        ]);
    }

    public function approve(Payment $payment, PaymentService $paymentService)
    {
        if ($payment->status !== Payment::STATUS_PENDING_VERIFICATION) {
            return back()->with('error', 'هذه العملية غير معلقة للتحقق.');
        }

        $paymentService->completeSuccessfulPayment($payment);

        return back()->with('success', 'تم تأكيد الدفع وتفعيل الكورس للطالب بنجاح.');
    }

    public function reject(Payment $payment)
    {
        if ($payment->status !== Payment::STATUS_PENDING_VERIFICATION) {
            return back()->with('error', 'هذه العملية غير معلقة للتحقق.');
        }

        $payment->update([
            'status' => Payment::STATUS_FAILED,
        ]);

        return back()->with('success', 'تم رفض إيصال التحويل بنجاح.');
    }
}
