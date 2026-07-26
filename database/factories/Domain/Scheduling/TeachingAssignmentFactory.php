<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Scheduling;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeachingAssignmentFactory extends Factory
{
    protected $model = TeachingAssignment::class;

    public function definition(): array
    {
        return [
            'teacher_id'            => User::factory(),
            'subject_id'            => Subject::factory(),
            'grade_level_id'        => GradeLevel::factory(),
            'private_monthly_price' => 90_000,
            'currency'              => 'QAR',
            'accepts_private'       => true,
            'is_active'             => true,
        ];
    }

    public function withoutPrivate(): static
    {
        return $this->state(['accepts_private' => false, 'private_monthly_price' => 0]);
    }
}
