<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Course;

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\Subject;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(4);

        return [
            'teacher_id'     => User::factory(),
            'subject_id'     => Subject::factory(),
            'title'          => $title,
            'slug'           => Str::slug($title) . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'description'    => $this->faker->paragraphs(3, true),
            'thumbnail'      => null,
            'price'          => $this->faker->randomElement([0, 10_000, 25_000, 50_000]),
            'discount_price' => null,
            'is_published'   => true,
            'grade_level'    => $this->faker->randomElement(['grade_10', 'grade_11', 'grade_12', 'all']),
            'level'          => $this->faker->randomElement(['beginner', 'intermediate', 'advanced']),
            'total_lessons'  => 0,
            'total_duration' => 0,
        ];
    }

    public function free(): static
    {
        return $this->state(['price' => 0, 'discount_price' => null]);
    }

    public function paid(int $priceHalala = 50_000): static
    {
        return $this->state(['price' => $priceHalala, 'discount_price' => null]);
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
