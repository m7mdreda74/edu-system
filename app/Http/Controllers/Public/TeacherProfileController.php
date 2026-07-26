<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Learning\Models\TeacherReview;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The teacher's shop window: intro video, background, what they teach, which
 * groups still have seats, and what a month costs. This is where a student
 * decides to book.
 */
class TeacherProfileController extends Controller
{
    private const DAY_NAMES = [
        0 => 'الأحد',
        1 => 'الإثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];

    public function show(Request $request, int $id): Response
    {
        $teacher = User::whereHas('roles', fn ($q) => $q->where('name', 'teacher'))
            ->where('is_active', true)
            ->findOrFail($id);

        $assignments = TeachingAssignment::with([
            'subject:id,name,name_en,icon',
            'gradeLevel:id,key,name',
            'groups' => fn ($q) => $q->where('is_active', true)
                ->with('schedules')
                ->withCount('activeBookings')
                ->orderBy('day_of_week')
                ->orderBy('start_time'),
        ])
            ->where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->get();

        // A student arriving from a subject page should land on that subject.
        $focusGradeKey  = $request->query('grade');
        $focusSubjectId = $request->query('subject');

        $student = $request->user();

        return Inertia::render('Public/TeacherProfile', [
            'teacher' => [
                'id'                    => $teacher->id,
                'name'                  => $teacher->name,
                'headline'              => $teacher->headline,
                'bio'                   => $teacher->bio,
                'avatar'                => $teacher->avatar,
                'intro_video_url'       => $teacher->intro_video_url,
                'intro_video_thumbnail' => $teacher->intro_video_thumbnail,
                'years_experience'      => $teacher->years_experience,
                'rating'                => $teacher->averageRating(),
                'students_count'        => $teacher->activeStudentsCount(),
            ],
            'assignments' => $assignments->map(fn (TeachingAssignment $assignment) => [
                'id'                    => $assignment->id,
                'subject'               => $assignment->subject?->only(['id', 'name', 'name_en', 'icon']),
                'grade'                 => $assignment->gradeLevel?->only(['id', 'key', 'name']),
                'accepts_private'       => $assignment->offersPrivate(),
                'private_monthly_price' => $assignment->private_monthly_price,
                'currency'              => $assignment->currency,
                'groups'                => $assignment->groups->map(fn (TeachingGroup $group) => [
                    'id'            => $group->id,
                    'name'          => $group->name,
                    'monthly_price' => $group->monthly_price,
                    'currency'      => $group->currency,
                    'capacity'      => $group->capacity,
                    'seats_left'    => max(0, $group->capacity - $group->active_bookings_count),
                    'timezone'      => $group->timezone,
                    'schedules'     => $this->formatSchedules($group),
                    'is_subscribed' => $student?->hasActiveSubscriptionTo($group) ?? false,
                ])->values(),
            ])->values(),
            'reviews' => TeacherReview::with('user:id,name,avatar')
                ->where('teacher_id', $teacher->id)
                ->where('is_approved', true)
                ->latest()
                ->take(20)
                ->get()
                ->map(fn (TeacherReview $review) => [
                    'id'      => $review->id,
                    'rating'  => $review->rating,
                    'comment' => $review->comment,
                    'author'  => $review->user?->only(['id', 'name', 'avatar']),
                    'date'    => $review->created_at?->toDateString(),
                ])->values(),
            'focus' => [
                'grade'   => $focusGradeKey,
                'subject' => $focusSubjectId ? (int) $focusSubjectId : null,
            ],
        ]);
    }

    /**
     * Groups can meet on several days; fall back to the group's own single-day
     * columns when no rows exist in `teaching_group_schedules`.
     *
     * @return array<int, array{day: int, day_name: string, start: string, end: string}>
     */
    private function formatSchedules(TeachingGroup $group): array
    {
        $schedules = $group->schedules->isNotEmpty()
            ? $group->schedules
            : collect([(object) [
                'day_of_week' => $group->day_of_week,
                'start_time'  => $group->start_time,
                'end_time'    => $group->end_time,
            ]]);

        return $schedules->map(fn ($schedule) => [
            'day'      => (int) $schedule->day_of_week,
            'day_name' => self::DAY_NAMES[(int) $schedule->day_of_week] ?? '',
            'start'    => substr((string) $schedule->start_time, 0, 5),
            'end'      => substr((string) $schedule->end_time, 0, 5),
        ])->values()->all();
    }
}
