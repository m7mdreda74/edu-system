<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Payment\Services\PaymentService;
use App\Domain\Course\Models\Course;
use App\Domain\Payment\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $user   = auth()->user();
        $course = Course::with(['teacher:id,name', 'subject:id,name'])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        if ($user->isEnrolledIn($course)) {
            return Inertia::render('Checkout/AlreadyEnrolled', ['course' => $course]);
        }

        return Inertia::render('Checkout/Index', [
            'course' => $course,
        ]);
    }

    public function process(Request $request, string $slug): RedirectResponse
    {
        $validated = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ]);

        $user   = auth()->user();
        $course = Course::where('slug', $slug)->where('is_published', true)->firstOrFail();

        try {
            $result = $this->paymentService->initiateCheckout(
                user:       $user,
                course:     $course,
                couponCode: $validated['coupon_code'] ?? null,
            );

            // Redirect to gateway payment page
            return redirect()->away($result['redirect_url']);
        } catch (LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function success(Request $request): Response
    {
        // Called by gateway after successful payment
        // Actual enrollment happens via webhook (more reliable)
        return Inertia::render('Checkout/Success', [
            'session_id' => $request->query('session_id'),
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
        $payment = Payment::where('gateway_ref', $ref)->firstOrFail();
        $course  = Course::with(['teacher:id,name', 'subject:id,name'])->findOrFail($payment->course_id);

        return Inertia::render('Checkout/MockGateway', [
            'payment' => $payment,
            'course'  => $course,
        ]);
    }

    public function mockComplete(string $ref): RedirectResponse
    {
        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $ref,
                ]
            ]
        ]);

        $this->paymentService->processWebhookEvent($payload);

        return redirect()->route('checkout.success', ['session_id' => $ref]);
    }

    public function mockCancel(string $ref): RedirectResponse
    {
        $payment = Payment::where('gateway_ref', $ref)->firstOrFail();
        $payment->update(['status' => Payment::STATUS_FAILED]);

        return redirect()->route('checkout.cancel');
    }
}
