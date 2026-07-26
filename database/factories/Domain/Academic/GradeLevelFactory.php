<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Academic;

use App\Domain\Academic\Models\GradeLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradeLevelFactory extends Factory
{
    protected $model = GradeLevel::class;

    public function definition(): array
    {
        $number = $this->faker->unique()->numberBetween(1, 12);

        return [
            'key'       => "grade_{$number}",
            'name'      => "الصف {$number}",
            'name_en'   => "Grade {$number}",
            'stage'     => 'secondary',
            'is_active' => true,
        ];
    }
}
