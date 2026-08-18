<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Subscription\Models\Subscription;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Step three: a subject opens the teachers who teach it to this grade.
 *
 * Each card carries the teacher's intro video and rating so a student can judge
 * the teaching style before committing to a month — which is the whole point of
 * the flow.
 */
class SubjectTeachersController extends Controller
{
    public function show(Request $request, string $gradeKey, int $subjectId): Response
    {
        $student = $request->user();
        $grade = GradeLevel::where('key', $gradeKey)
            ->where('is_active', true)
            ->firstOrFail();

        $subject = Subject::where('is_active', true)->findOrFail($subjectId);

        $assignments = TeachingAssignment::with([
            'teacher' => fn ($query) => $query
                ->select(['id', 'name', 'bio', 'headline', 'avatar', 'intro_video_url', 'intro_video_thumbnail', 'years_experience'])
                ->withAvg([
                    'reviewsReceived as approved_rating' => fn ($reviews) => $reviews->where('is_approved', true),
                ], 'rating')
                ->withCount([
                    'reviewsReceived as approved_reviews_count' => fn ($reviews) => $reviews->where('is_approved', true),
                ]),
            'groups' => fn ($q) => $q->where('is_active', true)->with('schedules')->withCount('activeBookings'),
        ])
            ->where('grade_level_id', $grade->id)
            ->where('subject_id', $subject->id)
            ->where('is_active', true)
            ->whereHas('teacher', fn ($q) => $q->where('is_active', true))
            ->get();

        // Which teacher of this subject the student already studies with, if any.
        $subscribedTeacherId = $student
            ? Subscription::active()->where("student_id", $student->id)
                ->whereHas("assignment", fn ($q) => $q->where("subject_id", $subject->id))
                ->with("assignment:id,teacher_id")
                ->first()?->assignment?->teacher_id
            : null;

        $teachers = $assignments->map(function (TeachingAssignment $assignment) use ($subscribedTeacherId, $subject) {
            $teacher = $assignment->teacher;
            $groups  = $assignment->groups;

            return [
                'assignment_id'         => $assignment->id,
                'id'                    => $teacher->id,
                'name'                  => $teacher->name,
                'headline'              => $teacher->headline,
                'bio'                   => $teacher->bio,
                'avatar'                => $teacher->avatar,
                'intro_video_url'       => $teacher->intro_video_url,
                'intro_video_thumbnail' => $teacher->intro_video_thumbnail,
                'years_experience'      => $teacher->years_experience,
                'subject'               => [
                    'id'      => $subject->id,
                    'name'    => $subject->name,
                    'name_en' => $subject->name_en,
                    'icon'    => $subject->icon,
                    'image'   => $subject->image,
                ],
                'rating'                => round((float) ($teacher->approved_rating ?? 0), 1),
                'reviews_count'         => (int) ($teacher->approved_reviews_count ?? 0),
                'groups_count'          => $groups->count(),
                'cheapest_monthly'      => $groups->min('monthly_price'),
                'accepts_private'       => $assignment->offersPrivate(),
                'private_monthly_price' => $assignment->private_monthly_price,
                'has_free_seats'        => $groups->contains(fn ($g) => $g->active_bookings_count < $g->capacity),
                'is_subscribed'         => $subscribedTeacherId === $teacher->id,
                'first_group_id'        => $groups->first(fn ($g) => $g->active_bookings_count < $g->capacity)?->id,
            ];
        })->values();

        return Inertia::render('Public/SubjectTeachers', [
            'grade'    => [
                'key'  => $grade->key,
                'name' => $grade->name,
            ],
            'subject'  => [
                'id'      => $subject->id,
                'name'    => $subject->name,
                'name_en' => $subject->name_en,
                'icon'    => $subject->icon,
                'image'   => $subject->image,
            ],
            'teachers' => $teachers,
            'subscribedTeacherId' => $subscribedTeacherId,
            'isStudent' => $student?->hasRole("student") ?? false,
        ]);
    }
}
