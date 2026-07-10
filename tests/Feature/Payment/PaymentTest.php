<?php

declare(strict_types=1);

use App\Application\Payment\Services\PaymentService;
use App\Domain\Course\Models\Course;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Payment / Webhook Tests ──────────────────────────────────────────────────

describe('Webhook Idempotency', function () {

    it('processes a webhook event only once', function () {
        $student = User::factory()->create();
        $course  = Course::factory()->create(['price' => 10_000, 'is_published' => true]);

        // Create a pending payment with a known gateway_ref
        $payment = Payment::factory()->create([
            'user_id'     => $student->id,
            'course_id'   => $course->id,
            'amount'      => 10_000,
            'status'      => Payment::STATUS_PENDING,
            'gateway_ref' => 'pi_test_idempotent_123',
            'gateway'     => 'stripe',
        ]);

        $paymentService = app(PaymentService::class);

        $stripePayload = json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test_idempotent_123']],
        ]);

        // Process webhook twice
        $paymentService->processWebhookEvent($stripePayload);
        $paymentService->processWebhookEvent($stripePayload);

        // Payment must be marked paid exactly once
        $this->assertDatabaseHas('payments', [
            'id'     => $payment->id,
            'status' => Payment::STATUS_PAID,
        ]);

        // Enrollment must exist exactly once
        $this->assertDatabaseCount('enrollments', 1);

        // Invoice generated exactly once
        $this->assertDatabaseCount('invoices', 1);
    });

    it('auto-enrolls student after successful payment', function () {
        $student = User::factory()->create();
        $course  = Course::factory()->create(['price' => 10_000, 'is_published' => true]);

        Payment::factory()->create([
            'user_id'     => $student->id,
            'course_id'   => $course->id,
            'amount'      => 10_000,
            'status'      => Payment::STATUS_PENDING,
            'gateway_ref' => 'pi_test_enroll_456',
            'gateway'     => 'stripe',
        ]);

        $paymentService = app(PaymentService::class);

        $payload = json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test_enroll_456']],
        ]);

        $paymentService->processWebhookEvent($payload);

        // Student should be enrolled automatically
        $this->assertDatabaseHas('enrollments', [
            'user_id'   => $student->id,
            'course_id' => $course->id,
        ]);
    });

    it('ignores unknown gateway references gracefully', function () {
        $paymentService = app(PaymentService::class);

        $payload = json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_unknown_ref']],
        ]);

        // Should NOT throw — gracefully ignores unknown refs
        expect(fn () => $paymentService->processWebhookEvent($payload))
            ->not->toThrow(\Throwable::class);
    });

});

describe('Coupon Domain Logic', function () {

    it('applies percentage discount correctly using integer arithmetic', function () {
        $coupon = \App\Domain\Payment\Models\Coupon::factory()->create([
            'discount_percent' => 25,
            'is_active'        => true,
        ]);

        // 100 QAR = 10000 halala; 25% off = 75 QAR = 7500 halala
        $result = $coupon->applyDiscount(10_000);

        expect($result)->toBe(7_500);
    });

    it('rejects expired coupons', function () {
        $coupon = \App\Domain\Payment\Models\Coupon::factory()->create([
            'is_active'  => true,
            'expires_at' => now()->subDay(), // expired yesterday
        ]);

        expect($coupon->isUsable())->toBeFalse();
    });

    it('rejects exhausted coupons', function () {
        $coupon = \App\Domain\Payment\Models\Coupon::factory()->create([
            'is_active'   => true,
            'usage_limit' => 5,
            'used_count'  => 5,
        ]);

        expect($coupon->isUsable())->toBeFalse();
    });

    it('never returns a negative price after discount', function () {
        $coupon = \App\Domain\Payment\Models\Coupon::factory()->create([
            'discount_percent' => 110, // Impossible discount
            'is_active'        => true,
        ]);

        $result = $coupon->applyDiscount(10_000);

        expect($result)->toBeGreaterThanOrEqual(0);
    });

});
