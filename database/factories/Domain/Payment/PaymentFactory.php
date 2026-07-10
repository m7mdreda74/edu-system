<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Payment;

use App\Domain\Course\Models\Course;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'course_id'       => Course::factory(),
            'coupon_id'       => null,
            'amount'          => 50_000,
            'original_amount' => 50_000,
            'currency'        => 'QAR',
            'gateway'         => 'stripe',
            'gateway_ref'     => 'pi_test_' . $this->faker->unique()->uuid(),
            'status'          => Payment::STATUS_PENDING,
            'paid_at'         => null,
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'status'  => Payment::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(['status' => Payment::STATUS_FAILED]);
    }

    public function refunded(): static
    {
        return $this->state(['status' => Payment::STATUS_REFUNDED]);
    }
}
