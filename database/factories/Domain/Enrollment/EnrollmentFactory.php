<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Enrollment;

use App\Domain\Course\Models\Course;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'course_id'        => Course::factory(),
            'progress_percent' => 0,
            'enrolled_at'      => now(),
            'completed_at'     => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'progress_percent' => 100,
            'completed_at'     => now(),
        ]);
    }

    public function inProgress(int $percent = 50): static
    {
        return $this->state([
            'progress_percent' => $percent,
            'completed_at'     => null,
        ]);
    }
}
