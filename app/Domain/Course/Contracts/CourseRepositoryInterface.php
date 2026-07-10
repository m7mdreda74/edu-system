<?php

declare(strict_types=1);

namespace App\Domain\Course\Contracts;

use App\Domain\Course\Models\Course;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CourseRepositoryInterface
{
    public function findBySlug(string $slug): Course;

    public function getPublished(array $filters = [], int $perPage = 12): LengthAwarePaginator;

    public function getFeatured(int $limit = 8): Collection;

    public function getByTeacher(int $teacherId, int $perPage = 12): LengthAwarePaginator;

    public function create(array $data): Course;

    public function update(Course $course, array $data): Course;
}
