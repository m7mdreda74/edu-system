<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Learning;

use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Learning\Models\GroupMaterial;
use App\Support\ArabicOrdinal;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupMaterialFactory extends Factory
{
    protected $model = GroupMaterial::class;

    public function definition(): array
    {
        return [
            'curriculum_unit_id' => CurriculumUnit::factory(),
            'order'              => 1,
            // Overriding `order` renames the lesson with it.
            'title'              => fn (array $attributes) => 'الدرس ' . ArabicOrdinal::masculine((int) $attributes['order']),
            'video_url'          => null,
            'duration_seconds'   => 600,
            'is_free_preview'    => false,
        ];
    }

    public function freePreview(): static
    {
        return $this->state(['is_free_preview' => true]);
    }

    public function withBooklet(): static
    {
        return $this->state(['attachment_path' => '/storage/booklets/sample.pdf']);
    }
}
