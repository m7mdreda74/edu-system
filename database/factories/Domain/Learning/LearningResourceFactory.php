<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Learning;

use App\Domain\Learning\Models\LearningResource;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LearningResourceFactory extends Factory
{
    protected $model = LearningResource::class;

    public function definition(): array
    {
        return [
            'owner_teacher_id' => User::factory(),
            'title' => 'ملف تعليمي '.$this->faker->unique()->numberBetween(1, 9999),
            'description' => 'مورد تعليمي قابل لإعادة الاستخدام.',
            'category' => 'مراجعة',
            'tags' => ['مراجعة', 'تدريب'],
            'file_path' => 'private://resources/sample.pdf',
            'original_name' => 'sample.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'visibility' => LearningResource::VISIBILITY_PRIVATE,
            'approval_status' => LearningResource::APPROVAL_NONE,
            'is_active' => true,
        ];
    }

    public function sharedPending(): static
    {
        return $this->state([
            'visibility' => LearningResource::VISIBILITY_SHARED,
            'approval_status' => LearningResource::APPROVAL_PENDING,
        ]);
    }

    public function sharedApproved(): static
    {
        return $this->state([
            'visibility' => LearningResource::VISIBILITY_SHARED,
            'approval_status' => LearningResource::APPROVAL_APPROVED,
            'approved_at' => now(),
        ]);
    }
}
