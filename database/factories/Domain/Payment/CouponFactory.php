<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Payment;

use App\Domain\Payment\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code'             => strtoupper($this->faker->unique()->lexify('????')),
            'discount_percent' => $this->faker->randomElement([10, 15, 20, 25, 30, 50]),
            'expires_at'       => now()->addDays(30),
            'usage_limit'      => null,
            'used_count'       => 0,
            'is_active'        => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function exhausted(): static
    {
        return $this->state(['usage_limit' => 5, 'used_count' => 5]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function noExpiry(): static
    {
        return $this->state(['expires_at' => null]);
    }
}
