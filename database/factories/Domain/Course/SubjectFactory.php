<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Course;

use App\Domain\Course\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'name'      => $this->faker->unique()->randomElement([
                'رياضيات', 'فيزياء', 'كيمياء', 'أحياء', 'لغة عربية',
                'لغة إنجليزية', 'تاريخ', 'جغرافيا', 'تربية إسلامية', 'حاسب آلي',
            ]),
            'icon'      => '📚',
            'is_active' => true,
        ];
    }
}
