<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Subscription;

use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $group = TeachingGroup::factory();

        return [
            'student_id'             => User::factory(),
            'type'                   => Subscription::TYPE_GROUP,
            'teaching_group_id'      => $group,
            // Keep the denormalised assignment consistent with the group.
            'teaching_assignment_id' => fn (array $attributes) => TeachingGroup::find($attributes['teaching_group_id'])?->teaching_assignment_id,
            'monthly_price'          => 45_000,
            'currency'               => 'QAR',
            'period_start'           => now()->startOfDay(),
            'period_end'             => now()->startOfDay()->addMonth(),
            'status'                 => Subscription::STATUS_PENDING,
            'auto_renew'             => false,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => Subscription::STATUS_ACTIVE]);
    }

    public function expired(): static
    {
        return $this->state([
            'status'       => Subscription::STATUS_EXPIRED,
            'period_start' => now()->subMonths(2)->startOfDay(),
            'period_end'   => now()->subMonth()->startOfDay(),
        ]);
    }

    public function private(): static
    {
        return $this->state([
            'type'              => Subscription::TYPE_PRIVATE,
            'teaching_group_id' => null,
        ]);
    }
}
