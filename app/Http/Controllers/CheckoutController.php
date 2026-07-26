<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Payment\Services\PaymentService;
use App\Domain\Payment\Models\Coupon;
use App\Domain\Payment\Models\Payment;
use App\Domain\Settings\Models\PlatformSetting;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Pays for one month of a subscription — either through a gateway or by
 * uploading a bank-transfer receipt for an admin to verify.
 *
 * A parent may pay on behalf of a linked student; every entry point checks that
 * link before letting the payment through.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function show(int $subscriptionId): Response
    {
        $subscription = $this->authorizeSubscription($subscriptionId);

        if ($subscription->status === Subscription::STATUS_ACTIVE) {
            return Inertia::render('Checkout/AlreadySubscribed', [
                'subscription' => $this->presentSubscription($subscription),
            ]);
        }

        $manualMethods = json_decode(
            PlatformSetting::where('key', 'manual_payment_methods')->value('value') ?: '[]',
            true,
        );

        return Inertia::render('Checkout/Index', [
            'subscription'  => $this->presentSubscription($subscription),
            'manualMethods' => $manualMethods,
        ]);
    }

    public function process(Request $request, int $subscriptionId): SymfonyResponse
    {
        $validated = $request->validate([
            'coupon_code'     => ['nullable', 'string', 'max:50'],
            'payment_method'  => ['nullable', 'string', 'in:gateway,manual'],
            'selected_method' => ['required_if:payment_method,manual'],
            'receipt'         => ['required_if:payment_method,manual', 'file', 'image', 'max:8192'],
        ]);

        $subscription = $this->authorizeSubscription($subscriptionId);

        if (($validated['payment_method'] ?? 'gateway') === 'manual') {
            return $this->processManualTransfer($request, $validated, $subscription);
        }

        try {
            $result = $this->paymentService->initiateCheckout(
                user:         $subscription->student,
                subscription: $subscription,
                couponCode:   $validated['coupon_code'] ?? null,
            );

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['redirect_url' => $result['redirect_url']]);
            }

            return Inertia::location($result['redirect_url']);
        } catch (LogicException $e) {
            return $this->fail($request, $e->getMessage());
        }
    }

    public function success(Request $request): Response
    {
        // Final processing and activation happen strictly via gateway webhooks.
        // The redirect is logged for the audit trail only.
        Log::info('Payment success redirect callback received.', [
            'payment_id' => $request->query('paymentId') ?? $request->query('payment_id'),
            'ip'         => $request->ip(),
            'user_id'    => Auth::id(),
        ]);

        return Inertia::render('Checkout/Success', [
            'session_id' => $request->query('paymentId')
                ?? $request->query('payment_id')
                ?? $request->query('session_id'),
        ]);
    }

    public function cancel(): Response
    {
        return Inertia::render('Checkout/Cancel');
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

    // ─── Mock gateway (local development only) ────────────────────

    public function mockGateway(string $ref): Response
    {
        $payment = Payment::with('subscription.assignment.subject', 'subscription.assignment.teacher')
            ->where('gateway_ref', $ref)
            ->firstOrFail();

        return Inertia::render('Checkout/MockGateway', [
            'payment'      => $payment,
            'subscription' => $payment->subscription
                ? $this->presentSubscription($payment->subscription)
                : null,
        ]);
    }

    public function mockComplete(string $ref): RedirectResponse
    {
        $gatewayName = $this->paymentService->getGateway()->getGatewayName();

        $payload = $gatewayName === 'fatora'
            ? json_encode([
                'response_code' => '000',
                'order_id'      => $ref,
                'event'         => 'payment_completed',
            ])
            : json_encode([
                'type' => 'checkout.session.completed',
                'data' => ['object' => ['id' => $ref, 'payment_intent' => $ref]],
            ]);

        $this->paymentService->processWebhookEvent($payload);

        return redirect()->route('checkout.success', ['session_id' => $ref]);
    }

    public function mockCancel(string $ref): RedirectResponse
    {
        Payment::where('gateway_ref', $ref)->firstOrFail()->update(['status' => Payment::STATUS_FAILED]);

        return redirect()->route('checkout.cancel');
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
            'assignment.gradeLevel:id,key,name',
            'group.schedules',
        ])->findOrFail($subscriptionId);

        if ($subscription->student_id === $user->id) {
            return $subscription;
        }

        $isLinkedParent = ParentStudentLink::where('parent_user_id', $user->id)
            ->where('student_user_id', $subscription->student_id)
            ->exists();

        abort_unless($isLinkedParent, 403, 'غير مصرح لك بإتمام عملية الدفع لهذا الاشتراك.');

        return $subscription;
    }

    /** Bank/wallet transfer: store the receipt and queue it for admin review. */
    private function processManualTransfer(Request $request, array $validated, Subscription $subscription): SymfonyResponse
    {
        try {
            if ($subscription->status === Subscription::STATUS_ACTIVE) {
                throw new LogicException('هذا الاشتراك مفعّل بالفعل.');
            }

            $selected = is_string($validated['selected_method'])
                ? (json_decode($validated['selected_method'], true) ?: [])
                : $validated['selected_method'];

            $configured = json_decode(
                PlatformSetting::where('key', 'manual_payment_methods')->value('value') ?: '[]',
                true,
            );

            $method = collect($configured)->first(fn ($m) => ($m['name'] ?? null) === ($selected['name'] ?? null)
                && ($m['account_number'] ?? null) === ($selected['account_number'] ?? null));

            if (! $method) {
                throw new LogicException('وسيلة التحويل المختارة غير متاحة. حدّث الصفحة وحاول مرة أخرى.');
            }

            $originalAmount = $subscription->monthly_price;
            $finalAmount    = $originalAmount;
            $coupon         = null;

            if (! empty($validated['coupon_code'])) {
                /** @var Coupon|null $coupon */
                $coupon = Coupon::where('code', strtoupper(trim($validated['coupon_code'])))->first();

                if (! $coupon || ! $coupon->isUsable()) {
                    throw new LogicException('كود الخصم غير صحيح أو منتهي الصلاحية.');
                }

                $finalAmount = $coupon->applyDiscount($originalAmount);
            }

            $payment = Payment::create([
                'user_id'         => $subscription->student_id,
                'subscription_id' => $subscription->id,
                'coupon_id'       => $coupon?->id,
                'amount'          => $finalAmount,
                'original_amount' => $originalAmount,
                'currency'        => $subscription->currency ?? 'QAR',
                'gateway'         => 'manual',
                'gateway_ref'     => ($method['type'] ?? 'wallet') . ': ' . ($method['name'] ?? 'تحويل يدوي'),
                'status'          => Payment::STATUS_PENDING_VERIFICATION,
                'receipt_path'    => $request->file('receipt')->store('receipts', 'local'),
            ]);

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
