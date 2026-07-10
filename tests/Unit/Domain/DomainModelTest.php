<?php

declare(strict_types=1);

use App\Domain\Payment\Models\Coupon;
use App\Domain\Course\Models\Course;
use App\Domain\Enrollment\Models\Enrollment;

uses(\Tests\TestCase::class);

// ─── Pure Domain Unit Tests (No DB, No HTTP) ─────────────────────────────────

describe('Course Domain Model', function () {

    it('isFree() returns true when price is 0', function () {
        $course = new Course(['price' => 0, 'discount_price' => null]);
        expect($course->isFree())->toBeTrue();
    });

    it('isFree() uses discount_price when set', function () {
        $course = new Course(['price' => 50_000, 'discount_price' => 0]);
        expect($course->isFree())->toBeTrue();
    });

    it('getEffectivePrice() returns discount_price when set', function () {
        $course = new Course(['price' => 50_000, 'discount_price' => 30_000]);
        expect($course->getEffectivePrice())->toBe(30_000);
    });

    it('getEffectivePrice() returns price when no discount', function () {
        $course = new Course(['price' => 50_000, 'discount_price' => null]);
        expect($course->getEffectivePrice())->toBe(50_000);
    });

});

describe('Coupon Domain Model', function () {

    it('applyDiscount uses integer arithmetic — no float drift', function () {
        $coupon = new Coupon(['discount_percent' => 33]);

        // 99 halala × 33% = 32.67 → rounds to 33, result = 66
        $result = $coupon->applyDiscount(99);
        expect($result)->toBeInt();
        expect($result)->toBeGreaterThanOrEqual(0);
    });

    it('applyDiscount never returns negative', function () {
        $coupon = new Coupon(['discount_percent' => 200]);
        expect($coupon->applyDiscount(1_000))->toBe(0);
    });

    it('isExpired() correctly detects past expiry', function () {
        $coupon = new Coupon(['expires_at' => now()->subDay()]);
        expect($coupon->isExpired())->toBeTrue();
    });

    it('isExpired() returns false for future expiry', function () {
        $coupon = new Coupon(['expires_at' => now()->addDay()]);
        expect($coupon->isExpired())->toBeFalse();
    });

    it('isExpired() returns false when no expiry set', function () {
        $coupon = new Coupon(['expires_at' => null]);
        expect($coupon->isExpired())->toBeFalse();
    });

    it('isExhausted() detects when limit is reached', function () {
        $coupon = new Coupon(['usage_limit' => 10, 'used_count' => 10]);
        expect($coupon->isExhausted())->toBeTrue();
    });

    it('isExhausted() returns false when no limit', function () {
        $coupon = new Coupon(['usage_limit' => null, 'used_count' => 999]);
        expect($coupon->isExhausted())->toBeFalse();
    });

});

describe('Enrollment Progress', function () {

    it('isCompleted() is based on completed_at not progress_percent', function () {
        $enrollment = new Enrollment(['completed_at' => now(), 'progress_percent' => 100]);
        expect($enrollment->isCompleted())->toBeTrue();
    });

    it('isCompleted() returns false when completed_at is null', function () {
        $enrollment = new Enrollment(['completed_at' => null, 'progress_percent' => 100]);
        expect($enrollment->isCompleted())->toBeFalse();
    });

});
