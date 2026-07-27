<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Academic;

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Support\ArabicOrdinal;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurriculumUnitFactory extends Factory
{
    protected $model = CurriculumUnit::class;

    public function definition(): array
    {
        return [
            'teaching_assignment_id' => TeachingAssignment::factory(),
            // The school calendar ships with its migration, so a term is always there.
            'academic_term_id'       => fn () => AcademicTerm::currentOrNext()?->id,
            'order'                  => 1,
            // Overriding `order` renames the unit with it.
            'title'                  => fn (array $attributes) => 'الوحدة ' . ArabicOrdinal::feminine((int) $attributes['order']),
            'is_published'           => true,
        ];
    }

    public function inTerm(AcademicTerm $term): static
    {
        return $this->state(['academic_term_id' => $term->id]);
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
