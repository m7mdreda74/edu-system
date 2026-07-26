<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Scheduling;

use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeachingGroupFactory extends Factory
{
    protected $model = TeachingGroup::class;

    public function definition(): array
    {
        return [
            'teaching_assignment_id' => TeachingAssignment::factory(),
            'name'                   => 'مجموعة ' . $this->faker->unique()->numberBetween(1, 9999),
            'capacity'               => 20,
            'monthly_price'          => 45_000,
            'currency'               => 'QAR',
            'day_of_week'            => 0,
            'start_time'             => '16:00:00',
            'end_time'               => '17:30:00',
            'duration_minutes'       => 90,
            'timezone'               => 'Asia/Qatar',
            'is_active'              => true,
        ];
    }

    public function free(): static
    {
        return $this->state(['monthly_price' => 0]);
    }

    public function full(): static
    {
        return $this->state(['capacity' => 0]);
    }
}
