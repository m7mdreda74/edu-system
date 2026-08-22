<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\TeacherReview;
use App\Domain\Scheduling\Models\PrivateLessonRequest;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The teacher's shop window: intro video, cover, what they teach, which
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
            'privateSlots' => fn ($q) => $q
                ->where('status', 'available')
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at'),
        ])
            ->where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->get();

        $freeRecordingsByAssignment = GroupMaterial::query()
            ->with([
                'unit:id,teaching_assignment_id',
                'liveSession:id,lesson_id,recording_url,ended_at,is_published_as_lesson',
            ])
            ->where('is_free_preview', true)
            ->whereNotNull('video_url')
            ->whereHas('unit', fn ($query) => $query
                ->whereIn('teaching_assignment_id', $assignments->pluck('id')->all()))
            ->whereHas('liveSession', fn ($query) => $query
                ->where('is_published_as_lesson', true)
                ->whereNotNull('recording_url'))
            ->latest('updated_at')
            ->get()
            ->groupBy(fn (GroupMaterial $material): int => (int) $material->unit->teaching_assignment_id);

        // A student arriving from a subject page should land on that subject.
        $focus = $request->validate([
            'grade' => ['nullable', 'string', 'max:20'],
            'subject' => ['nullable', 'integer', 'min:1'],
        ]);
        $focusGradeKey = $focus['grade'] ?? null;
        $focusSubjectId = $focus['subject'] ?? null;

        $student = $request->user();
        $isStudent = $student?->hasRole('student') ?? false;
        $privateRequests = $student?->hasRole('student')
            ? PrivateLessonRequest::where('student_id', $student->id)
                ->where('status', PrivateLessonRequest::STATUS_PENDING)
                ->whereIn('teaching_assignment_id', $assignments->pluck('id'))
                ->get()
                ->keyBy('teaching_assignment_id')
            : collect();
        $freeIntroBooking = $isStudent
            ? SessionBooking::with('privateSlot:id,teaching_assignment_id,starts_at,ends_at,timezone,is_free_intro')
                ->where('student_id', $student->id)
                ->where('status', 'confirmed')
                ->whereHas('privateSlot', fn ($slot) => $slot
                    ->where('is_free_intro', true)
                    ->whereHas('assignment', fn ($assignment) => $assignment->where('teacher_id', $teacher->id)))
                ->latest('booked_at')
                ->first()
            : null;
        $privateSubscriptions = $isStudent
            ? Subscription::where('student_id', $student->id)
                ->where('type', Subscription::TYPE_PRIVATE)
                ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_PENDING])
                ->whereIn('teaching_assignment_id', $assignments->pluck('id'))
                ->get()
                ->filter(fn (Subscription $subscription) => $subscription->isPending() || $subscription->isActive())
                ->sortBy(fn (Subscription $subscription) => $subscription->isActive() ? 0 : 1)
                ->keyBy('teaching_assignment_id')
            : collect();

        return Inertia::render('Public/TeacherProfile', [
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'headline' => $teacher->headline,
                'bio' => $teacher->bio,
                'avatar' => $teacher->avatar,
                'profile_cover' => $teacher->profile_cover,
                'intro_video_url' => $teacher->intro_video_url,
                'intro_video_thumbnail' => $teacher->intro_video_thumbnail,
                'years_experience' => $teacher->years_experience,
                'rating' => $teacher->averageRating(),
                'students_count' => $teacher->activeStudentsCount(),
            ],
            'assignments' => $assignments->map(fn (TeachingAssignment $assignment) => [
                'id' => $assignment->id,
                'subject' => $assignment->subject?->only(['id', 'name', 'name_en', 'icon']),
                'grade' => $assignment->gradeLevel?->only(['id', 'key', 'name']),
                'accepts_private' => $assignment->offersPrivate(),
                'has_private_subscription' => ($privateSubscription = $privateSubscriptions->get($assignment->id))?->isActive() ?? false,
                'private_subscription' => $privateSubscription ? [
                    'id' => $privateSubscription->id,
                    'status' => $privateSubscription->status,
                ] : null,
                'private_monthly_price' => $assignment->private_monthly_price,
                'currency' => $assignment->currency,
                'private_request' => ($privateRequest = $privateRequests->get($assignment->id)) ? [
                    'id' => $privateRequest->id,
                    'status' => $privateRequest->status,
                    'conversation_id' => $privateRequest->conversation_id,
                ] : null,
                // Free-intro availability belongs to the selected assignment.
                // The booking rule is still one intro per teacher, so expose
                // that state per tab as well.
                'free_intro_booking' => $freeIntroBooking?->privateSlot?->teaching_assignment_id === $assignment->id
                    && $freeIntroBooking->privateSlot ? [
                        'id' => $freeIntroBooking->id,
                        'starts_at' => $freeIntroBooking->privateSlot->starts_at?->toIso8601String(),
                        'ends_at' => $freeIntroBooking->privateSlot->ends_at?->toIso8601String(),
                    ] : null,
                'free_intro_used' => (bool) $freeIntroBooking,
                'free_recordings' => $freeRecordingsByAssignment
                    ->get($assignment->id, collect())
                    ->take(3)
                    ->map(fn (GroupMaterial $material) => [
                        'id' => $material->id,
                        'title' => $material->title,
                        'description' => $material->description,
                        'duration_seconds' => $material->duration_seconds,
                        'published_at' => $material->liveSession?->ended_at?->toIso8601String()
                            ?? $material->updated_at?->toIso8601String(),
                        'stream_url' => URL::temporarySignedRoute(
                            'public.free-recordings.stream',
                            now()->addMinutes(30),
                            ['materialId' => $material->id],
                        ),
                    ])->values(),
                'free_intro_slots' => $assignment->privateSlots->where('is_free_intro', true)->map(fn ($slot) => [
                    'id' => $slot->id,
                    'starts_at' => $slot->starts_at?->toIso8601String(),
                    'ends_at' => $slot->ends_at?->toIso8601String(),
                    'timezone' => $slot->timezone,
                ])->values(),
                'private_slots' => $assignment->privateSlots->where('is_free_intro', false)->map(fn ($slot) => [
                    'id' => $slot->id,
                    'starts_at' => $slot->starts_at?->toIso8601String(),
                    'ends_at' => $slot->ends_at?->toIso8601String(),
                    'timezone' => $slot->timezone,
                ])->values(),
                'groups' => $assignment->groups
                    ->filter(fn (TeachingGroup $group) => $group->active_bookings_count < $group->capacity
                        || ($student?->hasActiveSubscriptionTo($group) ?? false))
                    ->map(fn (TeachingGroup $group) => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'monthly_price' => $group->monthly_price,
                        'currency' => $group->currency,
                        'capacity' => $group->capacity,
                        'seats_left' => max(0, $group->capacity - $group->active_bookings_count),
                        'timezone' => $group->timezone,
                        'schedules' => $this->formatSchedules($group),
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
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'author' => $review->user?->only(['id', 'name', 'avatar']),
                    'date' => $review->created_at?->toDateString(),
                ])->values(),
            'focus' => [
                'grade' => $focusGradeKey,
                'subject' => $focusSubjectId ? (int) $focusSubjectId : null,
            ],
            'freeIntroBooking' => $freeIntroBooking?->privateSlot ? [
                'id' => $freeIntroBooking->id,
                'starts_at' => $freeIntroBooking->privateSlot->starts_at?->toIso8601String(),
                'ends_at' => $freeIntroBooking->privateSlot->ends_at?->toIso8601String(),
            ] : null,
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
            : ($group->duration_minutes > 0 ? collect([(object) [
                'day_of_week' => $group->day_of_week,
                'start_time' => $group->start_time,
                'end_time' => $group->end_time,
            ]]) : collect());

        return $schedules->map(fn ($schedule) => [
            'day' => (int) $schedule->day_of_week,
            'day_name' => self::DAY_NAMES[(int) $schedule->day_of_week] ?? '',
            'start' => substr((string) $schedule->start_time, 0, 5),
            'end' => substr((string) $schedule->end_time, 0, 5),
        ])->values()->all();
    }
}
