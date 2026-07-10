<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class TeacherProfileController extends Controller
{
    public function show(int $id): Response
    {
        $teacher = User::with([
            'coursesAsTeacher' => fn ($q) => $q
                ->where('is_published', true)
                ->withCount('enrollments')
                ->with(['subject', 'teacher']),
        ])
        ->whereHas('roles', fn ($q) => $q->where('name', 'teacher'))
        ->where('is_active', true)
        ->findOrFail($id);

        $courses       = $teacher->coursesAsTeacher;
        $totalStudents = $courses->sum('enrollments_count');
        $avgRating     = $courses->avg(fn ($c) => $c->getAverageRating()) ?? 0;

        return Inertia::render('Public/TeacherProfile', [
            'teacher'       => [
                'id'     => $teacher->id,
                'name'   => $teacher->name,
                'bio'    => $teacher->bio,
                'avatar' => $teacher->avatar,
            ],
            'courses'       => $courses->map(fn ($c) => [
                'id'              => $c->id,
                'slug'            => $c->slug,
                'title'           => $c->title,
                'thumbnail'       => $c->thumbnail,
                'price'           => $c->price,
                'discount_price'  => $c->discount_price,
                'effective_price' => $c->getEffectivePrice(),
                'is_free'         => $c->isFree(),
                'grade_level'     => $c->grade_level,
                'total_lessons'   => $c->total_lessons,
                'enrollments_count' => $c->enrollments_count,
                'teacher'         => ['name' => $teacher->name],
            ])->values(),
            'totalStudents' => $totalStudents,
            'totalCourses'  => $courses->count(),
            'averageRating' => round($avgRating, 1),
        ]);
    }
}
