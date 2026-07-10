<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Quiz;

use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'quiz_id'      => Quiz::factory(),
            'score'        => 0,
            'passed'       => false,
            'started_at'   => now(),
            'submitted_at' => null,
        ];
    }

    public function submitted(int $score = 80): static
    {
        return $this->state([
            'score'        => $score,
            'passed'       => $score >= 70,
            'submitted_at' => now(),
        ]);
    }

    public function passed(): static
    {
        return $this->submitted(100);
    }

    public function failed(): static
    {
        return $this->submitted(40);
    }
}
