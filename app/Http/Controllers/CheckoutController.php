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

        if ($user->isEnrolledIn($course)) {
            return Inertia::render('Checkout/AlreadyEnrolled', ['course' => $course]);
        }

        $manualMethodsJson = \App\Domain\Settings\Models\PlatformSetting::where('key', 'manual_payment_methods')->value('value');
        $manualMethods = json_decode($manualMethodsJson ?: '[]', true);

        return Inertia::render('Checkout/Index', [
            'course' => $course,
            'manualMethods' => $manualMethods,
        ]);
    }

    public function process(Request $request, string $slug): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'string', 'in:gateway,manual'],
            'selected_method' => ['required_if:payment_method,manual', 'array'],
            'receipt' => ['required_if:payment_method,manual', 'file', 'image', 'max:8192'],
        ]);

        /** @var \App\Domain\User\Models\User $user */
        $user   = Auth::user();
        /** @var Course $course */
        $course = Course::where('slug', $slug)->where('is_published', true)->firstOrFail();

        // 1. Handle Manual Payment Flow
        if (($validated['payment_method'] ?? 'gateway') === 'manual') {
            try {
                if ($user->isEnrolledIn($course)) {
                    throw new LogicException('أنت مسجل في هذا الكورس مسبقاً.');
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
                $path = $request->file('receipt')->store('receipts', 'public');

                Payment::create([
                    'user_id'         => $user->id,
                    'course_id'       => $course->id,
                    'coupon_id'       => $coupon?->id,
                    'amount'          => $finalAmount,
                    'original_amount' => $originalAmount,
                    'currency'        => 'QAR',
                    'gateway'         => 'manual',
                    'gateway_ref'     => $validated['selected_method']['name'] ?? 'تحويل يدوي',
                    'status'          => Payment::STATUS_PENDING_VERIFICATION,
                    'receipt_path'    => '/storage/' . $path,
                ]);

                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'redirect_url' => route('dashboard'),
                        'message' => 'تم رفع الإيصال بنجاح. سيتم مراجعته وتفعيل الكورس لك خلال لحظات.'
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
                user:       $user,
                course:     $course,
                couponCode: $validated['coupon_code'] ?? null,
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

        if ($paymentId) {
            try {
                $this->paymentService->verifyAndProcessPayment((string) $paymentId);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('MyFatoorah callback verification failed: ' . $e->getMessage());
            }
        } elseif ($localPaymentId) {
            try {
                $transactionId = $request->query('transaction_id');
                $this->paymentService->verifyAndProcessPaymentDirect((string) $localPaymentId, $transactionId);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Fatora callback verification failed: ' . $e->getMessage());
            }
        }

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
