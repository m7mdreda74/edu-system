<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Academic;

use App\Domain\Academic\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        $subjects = [
            ['الرياضيات', 'Mathematics', 'calculator'],
            ['الفيزياء', 'Physics', 'atom'],
            ['الكيمياء', 'Chemistry', 'flask'],
            ['الأحياء', 'Biology', 'dna'],
        ];

        [$name, $nameEn, $icon] = $this->faker->randomElement($subjects);

        return [
            'name'        => $name,
            'name_en'     => $nameEn,
            'grade_level' => 'all',
            'icon'        => $icon,
            'is_active'   => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
