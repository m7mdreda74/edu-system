<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Course\Contracts\CourseRepositoryInterface;
use App\Domain\Course\Models\Course;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Eloquent implementation of CourseRepositoryInterface.
 * Controllers never call Eloquent directly — they go through this Repository.
 * (Repository Pattern + Dependency Inversion Principle)
 */
class EloquentCourseRepository implements CourseRepositoryInterface
{
    public function findBySlug(string $slug): Course
    {
        return Course::with([
            'teacher:id,name,avatar,bio',
            'subject:id,name,name_en,icon',
            'lessons:id,course_id,title,duration_seconds,order,is_free_preview',
            'reviews' => fn($q) => $q->where('is_approved', true)->with('user:id,name,avatar'),
        ])
        ->where('slug', $slug)
        ->where('is_published', true)
        ->firstOrFail();
    }

    public function getPublished(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = Course::with(['teacher:id,name,avatar', 'subject:id,name,icon'])
            ->where('is_published', true)
            ->select([
                'id', 'teacher_id', 'subject_id', 'title', 'slug',
                'thumbnail', 'price', 'discount_price',
                'grade_level', 'level', 'total_duration', 'total_lessons',
            ]);

        // Apply filters
        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (! empty($filters['grade_level'])) {
            $query->where('grade_level', $filters['grade_level']);
        }

        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (! empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                  ->orWhere('description', 'like', $searchTerm)
                  ->orWhereHas('teacher', function ($q2) use ($searchTerm) {
                      $q2->where('name', 'like', $searchTerm);
                  })
                  ->orWhereHas('subject', function ($q3) use ($searchTerm) {
                      $q3->where('name', 'like', $searchTerm)
                         ->orWhere('name_en', 'like', $searchTerm);
                  });
            });
        }

        // Sorting
        $sort = $filters['sort'] ?? 'latest';
        match ($sort) {
            'price_asc'  => $query->orderByRaw('COALESCE(discount_price, price) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(discount_price, price) DESC'),
            'popular'    => $query->withCount('enrollments')->orderBy('enrollments_count', 'desc'),
            default      => $query->latest(),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    public function getFeatured(int $limit = 8): Collection
    {
        return Course::with(['teacher:id,name,avatar', 'subject:id,name,icon'])
            ->where('is_published', true)
            ->withCount('enrollments')
            ->orderBy('enrollments_count', 'desc')
            ->limit($limit)
            ->get([
                'id', 'teacher_id', 'subject_id', 'title', 'slug',
                'thumbnail', 'price', 'discount_price',
                'grade_level', 'level', 'total_lessons',
            ]);
    }

    public function getByTeacher(int $teacherId, int $perPage = 12): LengthAwarePaginator
    {
        return Course::with(['subject:id,name,icon'])
            ->where('teacher_id', $teacherId)
            ->where('is_published', true)
            ->withCount('enrollments')
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Course
    {
        return Course::create($data);
    }

    public function update(Course $course, array $data): Course
    {
        $course->update($data);

        return $course->fresh();
    }
}
