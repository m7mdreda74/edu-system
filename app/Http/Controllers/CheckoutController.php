<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Payment\Models\Coupon;
use App\Domain\Payment\Models\Payment;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Pays for one month of a subscription by uploading a Vodafone Cash receipt
 * for an admin to verify.
 *
 * A parent may pay on behalf of a linked student; every entry point checks that
 * link before letting the payment through.
 */
class CheckoutController extends Controller
{
    public function show(int $subscriptionId): Response
    {
        $subscription = $this->authorizeSubscription($subscriptionId);

        if ($subscription->status === Subscription::STATUS_ACTIVE) {
            return Inertia::render('Checkout/AlreadySubscribed', [
                'subscription' => $this->presentSubscription($subscription),
            ]);
        }

        return Inertia::render('Checkout/Index', [
            'subscription'       => $this->presentSubscription($subscription),
            'vodafoneCashNumber' => $subscription->assignment?->gradeLevel?->vodafone_cash_number,
        ]);
    }

    public function process(Request $request, int $subscriptionId): SymfonyResponse
    {
        $validated = $request->validate([
            'coupon_code'    => ['nullable', 'string', 'max:50'],
            'payment_method' => ['required', 'string', 'in:vodafone_cash'],
            'sender_phone'   => ['required', 'string', 'max:20', 'regex:/^(?:\+20|0020|0)1\d{9}$/'],
            'receipt'        => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ], [
            'sender_phone.regex' => 'أدخل رقم الهاتف الذي حوّلت منه بصيغة 01012345678.',
        ]);

        $subscription = $this->authorizeSubscription($subscriptionId);

        return $this->processVodafoneCashPayment($request, $validated, $subscription);
    }

    public function checkCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'coupon_code'     => ['required', 'string', 'max:50'],
            'subscription_id' => ['required', 'integer', 'exists:subscriptions,id'],
        ]);

        /** @var Coupon|null $coupon */
        $coupon = Coupon::where('code', strtoupper(trim($validated['coupon_code'])))->first();

        if (! $coupon || ! $coupon->isUsable()) {
            return response()->json(['error' => 'كود الخصم غير صحيح أو منتهي الصلاحية.'], 422);
        }

        $subscription = $this->authorizeSubscription((int) $validated['subscription_id']);

        return response()->json([
            'discount_percent' => $coupon->discount_percent,
            'discounted_price' => $coupon->applyDiscount($subscription->monthly_price),
        ]);
    }

    // ─── Internals ────────────────────────────────────────────────

    /**
     * The subscription must belong to the signed-in student, or to a student
     * this parent is linked to.
     */
    private function authorizeSubscription(int $subscriptionId): Subscription
    {
        /** @var User $user */
        $user = Auth::user();

        $subscription = Subscription::with([
            'student:id,name',
            'assignment.subject:id,name,icon',
            'assignment.teacher:id,name,avatar',
            'assignment.gradeLevel:id,key,name,vodafone_cash_number',
            'group.schedules',
        ])->findOrFail($subscriptionId);

        if ($subscription->student_id === $user->id) {
            return $subscription;
        }

        $isLinkedParent = ParentStudentLink::where('parent_user_id', $user->id)
            ->where('student_user_id', $subscription->student_id)
            ->whereNotNull('verified_at')
            ->exists();

        abort_unless($isLinkedParent, 403, 'غير مصرح لك بإتمام عملية الدفع لهذا الاشتراك.');

        return $subscription;
    }

    /** Vodafone Cash transfer: store the receipt and queue it for admin review. */
    private function processVodafoneCashPayment(Request $request, array $validated, Subscription $subscription): SymfonyResponse
    {
        try {
            if ($subscription->status === Subscription::STATUS_ACTIVE) {
                throw new LogicException('هذا الاشتراك مفعّل بالفعل.');
            }

            $payment = DB::transaction(function () use ($request, $validated, $subscription): Payment {
                /** @var Subscription $lockedSubscription */
                $lockedSubscription = Subscription::query()
                    ->lockForUpdate()
                    ->findOrFail($subscription->id);

                $lockedSubscription->loadMissing('assignment.gradeLevel:id,vodafone_cash_number');
                $recipientPhone = $lockedSubscription->assignment?->gradeLevel?->vodafone_cash_number;

                if (! is_string($recipientPhone) || ! preg_match('/^(?:\+20|0020|0)1\d{9}$/', $recipientPhone)) {
                    throw new LogicException('لم يتم ضبط رقم فودافون كاش لهذه المرحلة الدراسية بعد. تواصل مع إدارة المنصة.');
                }

                if ($lockedSubscription->status === Subscription::STATUS_ACTIVE) {
                    throw new LogicException('هذا الاشتراك مفعّل بالفعل.');
                }

                if ($lockedSubscription->monthly_price <= 0) {
                    throw new LogicException('هذا الاشتراك مجاني ولا يحتاج إلى دفع.');
                }

                $hasPendingReceipt = Payment::query()
                    ->where('user_id', $lockedSubscription->student_id)
                    ->where('subscription_id', $lockedSubscription->id)
                    ->where('status', Payment::STATUS_PENDING_VERIFICATION)
                    ->exists();

                if ($hasPendingReceipt) {
                    throw new LogicException('يوجد إيصال تحويل قيد المراجعة لهذا الاشتراك بالفعل.');
                }

                $originalAmount = (int) $lockedSubscription->monthly_price;
                $finalAmount = $originalAmount;
                $coupon = null;

                if (! empty($validated['coupon_code'])) {
                    /** @var Coupon|null $coupon */
                    $coupon = Coupon::where('code', strtoupper(trim($validated['coupon_code'])))
                        ->lockForUpdate()
                        ->first();

                    if (! $coupon || ! $coupon->isUsable()) {
                        throw new LogicException('كود الخصم غير صحيح أو منتهي الصلاحية.');
                    }

                    $finalAmount = $coupon->applyDiscount($originalAmount);
                }

                return Payment::create([
                    'user_id'         => $lockedSubscription->student_id,
                    'subscription_id' => $lockedSubscription->id,
                    'coupon_id'       => $coupon?->id,
                    'amount'          => $finalAmount,
                    'original_amount' => $originalAmount,
                    'currency'        => $lockedSubscription->currency ?? 'QAR',
                    'gateway'         => Payment::GATEWAY_VODAFONE_CASH,
                    'gateway_ref'     => 'Vodafone Cash: ' . $recipientPhone,
                    'sender_phone'    => $validated['sender_phone'],
                    'status'          => Payment::STATUS_PENDING_VERIFICATION,
                    'receipt_path'    => $request->file('receipt')->store('receipts', 'local'),
                ]);
            });

            $payment->load(['user', 'subscription']);

            foreach (User::role('admin')->get() as $admin) {
                $admin->notify(new \App\Domain\Communication\Notifications\ManualPaymentSubmittedNotification($payment));
            }

            $message = 'تم رفع الإيصال بنجاح. سيتم مراجعته وتفعيل الاشتراك خلال لحظات.';

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success'      => true,
                    'redirect_url' => route('student.my-classes'),
                    'message'      => $message,
                ]);
            }

            return redirect()->route('student.my-classes')->with('success', $message);
        } catch (LogicException $e) {
            return $this->fail($request, $e->getMessage());
        }
    }

    private function fail(Request $request, string $message): SymfonyResponse
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['error' => $message], 422);
        }

        return back()->with('error', $message);
    }

    /** @return array<string, mixed> */
    private function presentSubscription(Subscription $subscription): array
    {
        $group = $subscription->group;

        return [
            'id'            => $subscription->id,
            'type'          => $subscription->type,
            'status'        => $subscription->status,
            'label'         => $subscription->label(),
            'monthly_price' => $subscription->monthly_price,
            'currency'      => $subscription->currency,
            'period_start'  => $subscription->period_start?->toDateString(),
            'period_end'    => $subscription->period_end?->toDateString(),
            'student'       => $subscription->student?->only(['id', 'name']),
            'subject'       => $subscription->assignment?->subject?->only(['id', 'name', 'icon']),
            'grade'         => $subscription->assignment?->gradeLevel?->only(['key', 'name']),
            'teacher'       => $subscription->assignment?->teacher?->only(['id', 'name', 'avatar']),
            'group'         => $group ? [
                'id'        => $group->id,
                'name'      => $group->name,
                'schedules' => $group->schedules->map(fn ($s) => [
                    'day'   => (int) $s->day_of_week,
                    'start' => substr((string) $s->start_time, 0, 5),
                    'end'   => substr((string) $s->end_time, 0, 5),
                ])->values(),
            ] : null,
        ];
    }
}
