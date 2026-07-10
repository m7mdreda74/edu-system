<?php

declare(strict_types=1);

namespace App\Domain\Enrollment\DTOs;

/**
 * Data Transfer Object for enrollment creation.
 * Immutable — enforced by `readonly`.
 * Passed between layers instead of loose arrays.
 */
final readonly class CreateEnrollmentDTO
{
    public function __construct(
        public int  $userId,
        public int  $courseId,
        public ?int $paymentId = null,  // null for free enrollments
    ) {}

    public static function fromFreeEnrollment(int $userId, int $courseId): self
    {
        return new self(userId: $userId, courseId: $courseId);
    }

    public static function fromPayment(int $userId, int $courseId, int $paymentId): self
    {
        return new self(userId: $userId, courseId: $courseId, paymentId: $paymentId);
    }
}
