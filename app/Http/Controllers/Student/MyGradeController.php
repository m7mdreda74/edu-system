<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The student's own grade, laid out the way they think about it: every subject
 * on their curriculum, and under each one the teachers who teach it.
 *
 * Each subject says plainly whether they are covered — "مشترك مع أ. فلان" or
 * "غير مشترك مع أي معلم" — so the gap in their timetable is obvious at a
 * glance rather than something they have to work out.
 */
class MyGradeController extends Controller
{
    public function index(): Response
    {
        /** @var User $student */
        $student = Auth::user();

        $grade = $student->grade_level
            ? GradeLevel::where('key', $student->grade_level)->where('is_active', true)->first()
            : null;

        if (! $grade) {
            return Inertia::render('Student/MyGrade', [
                'grade'    => null,
                'subjects' => [],
                'grades'   => GradeLevel::where('is_active', true)->orderBy('id')->get(['key', 'name', 'stage']),
            ]);
        }

        // Which teacher, if any, this student is already studying each subject
        // with — keyed by subject so the lookup below stays flat.
        $subscribedTeachers = Subscription::active()
            ->where('student_id', $student->id)
            ->with('assignment:id,subject_id,teacher_id', 'assignment.teacher:id,name')
            ->get()
            ->mapWithKeys(fn (Subscription $s) => [
                $s->assignment?->subject_id => [
                    'teacher_id'   => $s->assignment?->teacher_id,
                    'teacher_name' => $s->assignment?->teacher?->name,
                    'group_id'     => $s->teaching_group_id,
                    'type'         => $s->type,
                ],
            ]);

        $assignments = TeachingAssignment::with([
            'teacher:id,name,bio,headline,avatar,intro_video_url,intro_video_thumbnail,years_experience',
            'groups' => fn ($q) => $q->where('is_active', true)->withCount('activeBookings'),
        ])
            ->where('grade_level_id', $grade->id)
            ->where('is_active', true)
            ->whereHas('teacher', fn ($q) => $q->where('is_active', true))
            ->get()
            ->groupBy('subject_id');

        $subjects = $grade->subjects()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['subjects.id', 'name', 'name_en', 'icon'])
            ->map(function (Subject $subject) use ($assignments, $subscribedTeachers, $grade) {
                $subscribed = $subscribedTeachers->get($subject->id);

                return [
                    'id'      => $subject->id,
                    'name'    => $subject->name,
                    'name_en' => $subject->name_en,
                    'icon'    => $subject->icon,

                    // The headline the student reads first.
                    'is_subscribed'    => $subscribed !== null,
                    'subscribed_with'  => $subscribed['teacher_name'] ?? null,
                    'learn_group_id'   => $subscribed['group_id'] ?? null,

                    'browse_url' => route('subjects.teachers', [
                        'gradeKey' => $grade->key,
                        'subject'  => $subject->id,
                    ]),

                    'teachers' => ($assignments->get($subject->id) ?? collect())
                        ->map(fn (TeachingAssignment $assignment) => $this->presentTeacher($assignment, $subscribed, $grade))
                        // The teacher they already study with sits first.
                        ->sortByDesc('is_subscribed')
                        ->values(),
                ];
            })
            ->values();

        return Inertia::render('Student/MyGrade', [
            'grade' => [
                'key'         => $grade->key,
                'name'        => $grade->name,
                'stage_label' => $grade->stageLabel(),
                'track_label' => $grade->trackLabel(),
            ],
            'subjects' => $subjects,
            'summary'  => [
                'total'      => $subjects->count(),
                'subscribed' => $subjects->where('is_subscribed', true)->count(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function presentTeacher(TeachingAssignment $assignment, ?array $subscribed, GradeLevel $grade): array
    {
        $teacher = $assignment->teacher;
        $groups  = $assignment->groups;

        return [
            'id'                    => $teacher->id,
            'assignment_id'         => $assignment->id,
            'name'                  => $teacher->name,
            'headline'              => $teacher->headline,
            'bio'                   => $teacher->bio,
            'avatar'                => $teacher->avatar,
            'intro_video_url'       => $teacher->intro_video_url,
            'intro_video_thumbnail' => $teacher->intro_video_thumbnail,
            'years_experience'      => $teacher->years_experience,
            'rating'                => $teacher->averageRating(),

            // Green when this is the teacher they study with, red otherwise.
            'is_subscribed' => ($subscribed['teacher_id'] ?? null) === $teacher->id,

            'cheapest_monthly'      => $groups->min('monthly_price'),
            'accepts_private'       => $assignment->offersPrivate(),
            'private_monthly_price' => $assignment->private_monthly_price,
            'has_free_seats'        => $groups->contains(fn ($g) => $g->active_bookings_count < $g->capacity),

            'groups' => $groups->map(fn ($g) => [
                'id'            => $g->id,
                'name'          => $g->name,
                'monthly_price' => $g->monthly_price,
                'seats_left'    => max(0, $g->capacity - $g->active_bookings_count),
            ])->values(),

            'profile_url' => route('teachers.show', [
                'id'      => $teacher->id,
                'grade'   => $grade->key,
                'subject' => $assignment->subject_id,
            ]),
        ];
    }
}
