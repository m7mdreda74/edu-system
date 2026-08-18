<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class TeacherDirectoryController extends Controller
{
    public function index(): Response
    {
        $teachers = User::role('teacher')
            ->where('is_active', true)
            ->withAvg([
                'reviewsReceived as approved_rating' => fn ($query) => $query->where('is_approved', true),
            ], 'rating')
            ->withCount([
                'reviewsReceived as approved_reviews_count' => fn ($query) => $query->where('is_approved', true),
            ])
            ->with([
                'teachingAssignments' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with([
                        'subject:id,name,name_en,icon,image',
                        'gradeLevel:id,key,name',
                        'groups' => fn ($groups) => $groups
                            ->where('is_active', true)
                            ->withCount('activeBookings'),
                    ]),
            ])
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'name',
                'bio',
                'headline',
                'avatar',
                'intro_video_url',
                'intro_video_thumbnail',
                'years_experience',
                'is_featured',
            ])
            ->map(function (User $teacher): array {
                $assignments = $teacher->teachingAssignments;
                $groups = $assignments->flatMap->groups;
                $subject = $assignments->first()?->subject;

                return [
                    'id'                    => $teacher->id,
                    'name'                  => $teacher->name,
                    'bio'                   => $teacher->bio,
                    'headline'              => $teacher->headline,
                    'avatar'                => $teacher->avatar,
                    'intro_video_url'       => $teacher->intro_video_url,
                    'intro_video_thumbnail' => $teacher->intro_video_thumbnail,
                    'years_experience'      => $teacher->years_experience,
                    'subject'               => $subject ? [
                        'id'      => $subject->id,
                        'name'    => $subject->name,
                        'name_en' => $subject->name_en,
                        'icon'    => $subject->icon,
                        'image'   => $subject->image,
                    ] : null,
                    'rating'                => round((float) ($teacher->approved_rating ?? 0), 1),
                    'reviews_count'         => (int) ($teacher->approved_reviews_count ?? 0),
                    'is_featured'            => (bool) $teacher->is_featured,
                    'subjects'              => $assignments->pluck('subject.name')->filter()->unique()->values(),
                    'grades'                => $assignments->pluck('gradeLevel.name')->filter()->unique()->values(),
                    'assignments_count'     => $assignments->count(),
                    'groups_count'          => $groups->count(),
                    'cheapest_monthly'      => $groups->min('monthly_price'),
                    'accepts_private'       => $assignments->contains(fn ($assignment) => $assignment->offersPrivate()),
                    'has_free_seats'        => $groups->contains(fn ($group) => $group->active_bookings_count < $group->capacity),
                ];
            })
            ->values();

        return Inertia::render('Public/Teachers', [
            'teachers' => $teachers,
        ]);
    }
}
