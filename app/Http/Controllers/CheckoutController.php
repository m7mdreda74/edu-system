<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Payment\Services\PaymentService;
use App\Domain\Course\Models\Course;
use App\Domain\Payment\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function show(string $slug): Response
    {
        /** @var \App\Domain\User\Models\User $user */
        $user   = Auth::user();
        $course = Course::with(['teacher:id,name', 'subject:id,name'])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $purchaseRequest = null;
        $purchaseRequestId = request()->query('purchase_request_id');
        if ($purchaseRequestId) {
            $purchaseRequest = \App\Domain\Enrollment\Models\PurchaseRequest::with('student')->findOrFail((int) $purchaseRequestId);
            // Verify link
            $isLinked = \App\Domain\User\Models\ParentStudentLink::where('parent_user_id', $user->id)
                ->where('student_user_id', $purchaseRequest->student_user_id)
                ->exists();
            if (! $isLinked) {
                abort(403, 'غير مصرح لك بإجراء عملية الدفع لهذا الطلب.');
            }
            if ($purchaseRequest->student->isEnrolledIn($course)) {
                return Inertia::render('Checkout/AlreadyEnrolled', ['course' => $course, 'student' => $purchaseRequest->student]);
            }
        } else {
            if ($user->isEnrolledIn($course)) {
                return Inertia::render('Checkout/AlreadyEnrolled', ['course' => $course]);
            }
        }

        $manualMethodsJson = \App\Domain\Settings\Models\PlatformSetting::where('key', 'manual_payment_methods')->value('value');
        $manualMethods = json_decode($manualMethodsJson ?: '[]', true);

        return Inertia::render('Checkout/Index', [
            'course' => $course,
            'manualMethods' => $manualMethods,
            'purchaseRequest' => $purchaseRequest,
        ]);
    }

    public function process(Request $request, string $slug): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'string', 'in:gateway,manual'],
            'selected_method' => ['required_if:payment_method,manual'],
            'receipt' => ['required_if:payment_method,manual', 'file', 'image', 'max:8192'],
            'purchase_request_id' => ['nullable', 'integer', 'exists:purchase_requests,id'],
        ]);

        /** @var \App\Domain\User\Models\User $user */
        $user   = Auth::user();

        if (isset($validated['selected_method']) && is_string($validated['selected_method'])) {
            $validated['selected_method'] = json_decode($validated['selected_method'], true) ?: [];
        }
        /** @var Course $course */
        $course = Course::where('slug', $slug)->where('is_published', true)->firstOrFail();

        $purchaseRequest = null;
        $targetUser = $user;

        if (!empty($validated['purchase_request_id'])) {
            $purchaseRequest = \App\Domain\Enrollment\Models\PurchaseRequest::findOrFail($validated['purchase_request_id']);
            
            // Verify link
            $isLinked = \App\Domain\User\Models\ParentStudentLink::where('parent_user_id', $user->id)
                ->where('student_user_id', $purchaseRequest->student_user_id)
                ->exists();
            if (! $isLinked) {
                throw new LogicException('غير مصرح لك بإتمام عملية الدفع لهذا الطلب.');
            }
            
            $targetUser = \App\Domain\User\Models\User::findOrFail($purchaseRequest->student_user_id);
        }

        // 1. Handle Manual Payment Flow
        if (($validated['payment_method'] ?? 'gateway') === 'manual') {
            try {
                if ($targetUser->isEnrolledIn($course)) {
                    throw new LogicException('الطالب مسجل في هذا الكورس مسبقاً.');
                }

                $configuredMethods = json_decode(\App\Domain\Settings\Models\PlatformSetting::where('key', 'manual_payment_methods')->value('value') ?: '[]', true);
                $selectedMethod = collect($configuredMethods)->first(function ($method) use ($validated) {
                    return ($method['name'] ?? null) === ($validated['selected_method']['name'] ?? null)
                        && ($method['account_number'] ?? null) === ($validated['selected_method']['account_number'] ?? null);
                });
                if (! $selectedMethod) {
                    throw new LogicException('وسيلة التحويل المختارة غير متاحة. حدّث الصفحة وحاول مرة أخرى.');
                }

                // Resolve coupon
                $coupon         = null;
                $originalAmount = $course->getEffectivePrice();
                $finalAmount    = $originalAmount;

                if (!empty($validated['coupon_code'])) {
                    /** @var \App\Domain\Payment\Models\Coupon $coupon */
                    $coupon = \App\Domain\Payment\Models\Coupon::where('code', strtoupper(trim($validated['coupon_code'])))->first();
                    if (! $coupon || ! $coupon->isUsable()) {
                        throw new LogicException('كود الخصم غير صحيح أو منتهي الصلاحية.');
                    }
                    $finalAmount = $coupon->applyDiscount($originalAmount);
                }

                // Upload receipt screenshot
                $path = $request->file('receipt')->store('receipts', 'local');

                $payment = Payment::create([
                    'user_id'         => $targetUser->id,
                    'course_id'       => $course->id,
                    'coupon_id'       => $coupon?->id,
                    'amount'          => $finalAmount,
                    'original_amount' => $originalAmount,
                    'currency'        => 'QAR',
                    'gateway'         => 'manual',
                    'gateway_ref'     => ($selectedMethod['type'] ?? 'wallet') . ': ' . ($selectedMethod['name'] ?? 'تحويل يدوي'),
                    'status'          => Payment::STATUS_PENDING_VERIFICATION,
                    'receipt_path'    => $path,
                    'purchase_request_id' => $purchaseRequest?->id,
                ]);

                $payment->load(['user', 'course']);
                foreach (\App\Domain\User\Models\User::role('admin')->get() as $admin) {
                    $admin->notify(new \App\Domain\Communication\Notifications\ManualPaymentSubmittedNotification($payment));
                }

                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'redirect_url' => route('dashboard'),
                        'message' => 'تم رفع الإيصال بنجاح. سيتم مراجعته وتفعيل الكورس للطالب خلال لحظات.'
                    ]);
                }

                return redirect()->route('dashboard')->with('success', 'تم رفع الإيصال بنجاح. سيتم مراجعته وتفعيل الكورس خلال لحظات.');
            } catch (LogicException $e) {
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['error' => $e->getMessage()], 422);
                }
                return back()->with('error', $e->getMessage());
            }
        }

        // 2. Handle Gateway Payment Flow
        try {
            $result = $this->paymentService->initiateCheckout(
                user:              $targetUser,
                course:            $course,
                couponCode:        $validated['coupon_code'] ?? null,
                purchaseRequestId: $purchaseRequest?->id,
            );

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'redirect_url' => $result['redirect_url']
                ]);
            }

            // Redirect to gateway payment page
            return Inertia::location($result['redirect_url']);
        } catch (LogicException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    public function success(Request $request): Response
    {
        $paymentId = $request->query('paymentId'); // MyFatoorah
        $localPaymentId = $request->query('payment_id'); // Fatora

        // Final payment processing and enrollment is handled strictly via Gateway Webhooks.
        // We log the redirect callback for visibility and audit trail tracking.
        \Illuminate\Support\Facades\Log::info('Payment success redirect callback received.', [
            'payment_id' => $paymentId ?? $localPaymentId,
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
        ]);

        return Inertia::render('Checkout/Success', [
            'session_id' => $paymentId ?? $localPaymentId ?? $request->query('session_id'),
        ]);
    }

    public function cancel(): Response
    {
        return Inertia::render('Checkout/Cancel');
    }

    public function checkCoupon(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'coupon_code' => ['required', 'string', 'max:50'],
            'course_id'   => ['required', 'integer', 'exists:courses,id'],
        ]);

        /** @var \App\Domain\Payment\Models\Coupon|null $coupon */
        $coupon = \App\Domain\Payment\Models\Coupon::where('code', strtoupper(trim($validated['coupon_code'])))->first();

        if (! $coupon || ! $coupon->isUsable()) {
            return response()->json(['error' => 'كود الخصم غير صحيح أو منتهي الصلاحية.'], 422);
        }

        $course = Course::findOrFail($validated['course_id']);
        $originalAmount = $course->getEffectivePrice();
        $discountedPrice = $coupon->applyDiscount($originalAmount);

        return response()->json([
            'discount_percent' => $coupon->discount_percent,
            'discounted_price' => $discountedPrice,
        ]);
    }

    public function mockGateway(string $ref): Response
    {
        /** @var Payment $payment */
        $payment = Payment::where('gateway_ref', $ref)->firstOrFail();
        $course  = Course::with(['teacher:id,name', 'subject:id,name'])->findOrFail($payment->course_id);

        return Inertia::render('Checkout/MockGateway', [
            'payment' => $payment,
            'course'  => $course,
        ]);
    }

    public function mockComplete(string $ref): RedirectResponse
    {
        $gatewayName = $this->paymentService->getGateway()->getGatewayName();

        if ($gatewayName === 'fatora') {
            $payload = json_encode([
                'response_code' => '000',
                'order_id' => $ref,
                'event' => 'payment_completed'
            ]);
        } else {
            // Default to Stripe structure
            $payload = json_encode([
                'type' => 'checkout.session.completed',
                'data' => [
                    'object' => [
                        'id' => $ref,
                        'payment_intent' => $ref,
                    ]
                ]
            ]);
        }

        $this->paymentService->processWebhookEvent($payload);

        return redirect()->route('checkout.success', ['session_id' => $ref]);
    }

    public function mockCancel(string $ref): RedirectResponse
    {
        /** @var Payment $payment */
        $payment = Payment::where('gateway_ref', $ref)->firstOrFail();
        $payment->update(['status' => Payment::STATUS_FAILED]);

        return redirect()->route('checkout.cancel');
    }
}
