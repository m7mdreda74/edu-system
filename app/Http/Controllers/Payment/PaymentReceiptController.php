<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Domain\Payment\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PaymentReceiptController extends Controller
{
    /**
     * Display the official electronic receipt / tax invoice for a payment.
     */
    public function show(Payment $payment): Response
    {
        /** @var User $user */
        $user = Auth::user();

        // Authorization check: Admin, the student himself, or a linked parent
        $isOwnerStudent = ($payment->user_id === $user->id);
        $isLinkedParent = false;

        if ($user->isParent()) {
            $isLinkedParent = ParentStudentLink::where('parent_user_id', $user->id)
                ->where('student_user_id', $payment->user_id)
                ->whereNotNull('verified_at')
                ->exists();
        }

        abort_unless($user->isAdmin() || $isOwnerStudent || $isLinkedParent, 403, 'غير مصرح لك باستعراض هذا الإيصال.');

        $payment->loadMissing([
            'user:id,name,email,phone,grade_level',
            'subscription.assignment.subject:id,name,icon',
            'subscription.assignment.teacher:id,name',
            'subscription.assignment.gradeLevel:id,name,key',
            'subscription.group:id,name,monthly_price',
            'invoice',
            'coupon:id,code,discount_percent',
        ]);

        // Auto-generate invoice if not exists yet
        $invoice = $payment->invoice;
        if (! $invoice) {
            $year = now()->year;
            $seq = str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
            $invoice = Invoice::create([
                'payment_id'     => $payment->id,
                'invoice_number' => "INV-{$year}-{$seq}",
                'issued_at'      => $payment->paid_at ?? now(),
            ]);
        }

        return Inertia::render('Payment/ReceiptView', [
            'payment' => [
                'id'             => $payment->id,
                'status'         => $payment->status,
                'is_paid'        => $payment->isPaid(),
                'amount'         => $payment->amount,
                'currency'       => $payment->currency ?? 'QAR',
                'gateway'        => $payment->gateway ?? 'skipcash',
                'gateway_ref'    => $payment->gateway_ref,
                'paid_at'        => $payment->paid_at?->toIso8601String() ?? $payment->created_at?->toIso8601String(),
                'invoice_number' => $invoice->invoice_number,
                'issued_at'      => $invoice->issued_at?->toIso8601String() ?? now()->toIso8601String(),
                'billing_period' => $payment->subscription?->type === 'term' ? 'باقة الفصل الدراسي (ترم كامل)' : 'باقة الاشتراك الشهري',
                'student'        => [
                    'id'          => $payment->user?->id,
                    'name'        => $payment->user?->name,
                    'phone'       => $payment->user?->phone,
                    'grade_level' => $payment->subscription?->assignment?->gradeLevel?->name ?? $payment->user?->grade_level,
                ],
                'course' => [
                    'subject' => $payment->subscription?->assignment?->subject?->name ?? 'مادة تعليمية',
                    'teacher' => $payment->subscription?->assignment?->teacher?->name ?? 'معلم المادة',
                    'group'   => $payment->subscription?->group?->name ?? 'المجموعة الأكاديمية',
                ],
                'coupon' => $payment->coupon ? [
                    'code'             => $payment->coupon->code,
                    'discount_percent' => $payment->coupon->discount_percent,
                ] : null,
            ],
        ]);
    }
}
