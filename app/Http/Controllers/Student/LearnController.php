<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Certificate\Services\CertificateService;
use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The study room: the syllabus of one teaching group, term by term, as the
 * student sees it — units, the lessons inside them, and the two unit exams.
 *
 * The syllabus hangs off the assignment rather than the slot, so the tree the
 * student reads here is the one the teacher wrote once in the curriculum
 * builder. Only this student's own progress, submissions and attempts are
 * folded into it.
 */
class LearnController extends Controller
{
    /** A lesson counts as done once this much of its video has been watched. */
    private const COMPLETION_RATIO = 0.9;

    public function __construct(
        private readonly CertificateService $certificates,
    ) {}

    public function show(Request $request, int $groupId): Response
    {
        /** @var User $user */
        $user  = Auth::user();
        $group = TeachingGroup::with(['assignment.teacher:id,name', 'assignment.subject:id,name,icon'])
            ->findOrFail($groupId);

        $this->authorizeAccess($user, $group);

        $terms        = $this->terms($group);
        $activeTermId = $this->activeTermId($request, $group, $terms);

        return Inertia::render('Student/Learn', [
            'group' => [
                'id'                     => $group->id,
                'name'                   => $group->name,
                'teaching_assignment_id' => (int) $group->teaching_assignment_id,
                'subject'                => $group->assignment?->subject?->only(['id', 'name', 'icon']),
                'teacher'                => $group->assignment?->teacher?->only(['id', 'name']),
            ],
            'terms'        => $terms->all(),
            'activeTermId' => $activeTermId,
            'units'        => $this->presentUnits($user, $group, $activeTermId),
            'progress'     => [
                'percent'           => $this->certificates->progressPercent($user, $group),
                'certificate_ready' => $this->certificates->isEligible($user, $group),
            ],
        ]);
    }

    /**
     * AJAX endpoint: update how far the student has watched.
     * Completion is decided server-side from the lesson's own duration —
     * the client never sends a "done" flag.
     */
    public function updateProgress(Request $request, int $groupId, int $materialId): JsonResponse
    {
        $validated = $request->validate([
            'watched_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        ]);

        /** @var User $user */
        $user  = Auth::user();
        $group = TeachingGroup::findOrFail($groupId);

        if (! $this->hasAccess($user, $group)) {
            return response()->json(['error' => 'اشتراكك في هذه المجموعة غير فعّال.'], 403);
        }

        $material = $group->materials()->findOrFail($materialId);

        $existing = LessonProgress::where('student_id', $user->id)
            ->where('lesson_id', $material->id)
            ->first();

        $isCompleted = $material->duration_seconds > 0
            && $validated['watched_seconds'] >= (int) floor($material->duration_seconds * self::COMPLETION_RATIO);

        LessonProgress::updateOrCreate(
            ['student_id' => $user->id, 'lesson_id' => $material->id],
            [
                // Never walk progress backwards if a stale ping arrives late.
                'watched_seconds' => max($validated['watched_seconds'], $existing->watched_seconds ?? 0),
                'is_completed'    => $isCompleted || (bool) ($existing->is_completed ?? false),
            ],
        );

        return response()->json([
            'progress_percent'  => $this->certificates->progressPercent($user, $group),
            'certificate_ready' => $this->certificates->isEligible($user, $group),
            // Finishing a lesson can unlock the next one and the whole next
            // unit, so the tree of the term being watched comes back rebuilt.
            'units' => $this->presentUnits($user, $group, $material->unit?->academic_term_id),
        ]);
    }

    /** Handles both "الواجب" and the answers to a unit's paper exam. */
    public function submitHomework(Request $request, int $groupId, int $worksheetId): RedirectResponse
    {
        $request->validate([
            'submitted_file' => ['required', 'file', 'mimes:pdf,docx,png,jpg,jpeg', 'max:10240'],
        ]);

        /** @var User $user */
        $user  = Auth::user();
        $group = TeachingGroup::findOrFail($groupId);

        $this->authorizeAccess($user, $group);

        $worksheet = $group->worksheets()->findOrFail($worksheetId);

        abort_if(! $worksheet->requires_submission, 403, 'هذا الشيت لا يتطلب تسليماً.');

        $path = $request->file('submitted_file')->store('submissions', 'public');

        WorksheetSubmission::updateOrCreate(
            ['worksheet_id' => $worksheet->id, 'student_id' => $user->id],
            [
                'submitted_file_path' => '/storage/' . $path,
                'submitted_at'        => now(),
                // Resubmitting clears the previous grade.
                'score'               => null,
                'teacher_feedback'    => null,
                'graded_at'           => null,
            ],
        );

        return back()->with('success', $worksheet->type === Worksheet::TYPE_PAPER_EXAM
            ? 'تم تسليم إجابة الاختبار بنجاح.'
            : 'تم تسليم الواجب بنجاح.');
    }

    // ─── Access ───────────────────────────────────────────────────

    /**
     * The syllabus belongs to the assignment, so any live subscription under it
     * opens the room — including a private student's, who has no group.
     * The group's own teacher and admins can always preview it.
     */
    private function hasAccess(User $user, TeachingGroup $group): bool
    {
        $group->loadMissing('assignment');

        return $user->isAdmin()
            || $user->id === $group->assignment?->teacher_id
            || $user->hasActiveSubscriptionToAssignment((int) $group->teaching_assignment_id);
    }

    private function authorizeAccess(User $user, TeachingGroup $group): void
    {
        abort_unless($this->hasAccess($user, $group), 403, 'يجب أن يكون لديك اشتراك فعّال في هذه المجموعة.');
    }

    // ─── Terms ────────────────────────────────────────────────────

    /**
     * Only the semesters this assignment actually has a syllabus for — a tab
     * that leads to an empty page is worse than no tab.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function terms(TeachingGroup $group): Collection
    {
        $counts = CurriculumUnit::where('teaching_assignment_id', $group->teaching_assignment_id)
            ->where('is_published', true)
            ->groupBy('academic_term_id')
            ->selectRaw('academic_term_id, count(*) as total')
            ->pluck('total', 'academic_term_id');

        return AcademicTerm::whereIn('id', $counts->keys()->all())
            ->orderBy('starts_on')
            ->get()
            ->map(fn (AcademicTerm $term) => [
                'id'          => $term->id,
                'name'        => $term->name,
                'year_label'  => $term->year_label,
                'term_number' => $term->term_number,
                'full_name'   => $term->fullName(),
                'is_current'  => (bool) $term->isCurrent(),
                'units_count' => (int) $counts[$term->id],
            ]);
    }

    /** @param  Collection<int, array<string, mixed>>  $terms */
    private function activeTermId(Request $request, TeachingGroup $group, Collection $terms): ?int
    {
        $available = $terms->pluck('id');

        // The tab the student clicked wins; then the term their slot runs in.
        foreach ([$request->integer('term'), (int) $group->academic_term_id] as $candidate) {
            if ($candidate && $available->contains($candidate)) {
                return $candidate;
            }
        }

        $current = AcademicTerm::currentOrNext()?->id;

        return $current && $available->contains($current)
            ? (int) $current
            : $terms->first()['id'] ?? null;
    }

    // ─── Presentation ─────────────────────────────────────────────

    /**
     * The unit tree of one term with this student's history folded in. Shared
     * by the page and by the progress ping, which redraws it after a lesson is
     * finished.
     *
     * @return array<int, array<string, mixed>>
     */
    private function presentUnits(User $user, TeachingGroup $group, ?int $termId): array
    {
        // No academic calendar, or nothing published yet — nothing to walk.
        if ($termId === null) {
            return [];
        }

        $units = CurriculumUnit::where('teaching_assignment_id', $group->teaching_assignment_id)
            ->where('academic_term_id', $termId)
            ->where('is_published', true)
            ->with([
                'lessons.homework',
                // A draft exam — no questions, switched off — does not exist
                // for the student until the teacher activates it.
                'electronicExam' => fn ($query) => $query->where('is_active', true)->withCount('questions'),
                'paperExam',
            ])
            ->orderBy('order')
            ->get();

        $lessons     = $units->flatMap(fn (CurriculumUnit $unit) => $unit->lessons);
        $progress    = $this->progressMap($user, $lessons);
        $submissions = $this->submissionMap($user, $units, $lessons);
        $attempts    = $this->attemptMap($user, $units);

        $previousCleared = true; // the first unit is never gated
        $tree            = [];

        foreach ($units as $unit) {
            $isLocked   = ! $previousCleared;
            $unitLessons = $this->presentLessons($unit, $progress, $submissions, $isLocked);
            $done        = count(array_filter($unitLessons, fn (array $lesson) => $lesson['is_completed']));

            $tree[] = [
                'id'                      => $unit->id,
                'order'                   => $unit->order,
                'title'                   => $unit->title,
                'description'             => $unit->description,
                'is_locked'               => $isLocked,
                'is_completed'            => $unitLessons !== [] && $done === count($unitLessons),
                'lessons_count'           => count($unitLessons),
                'completed_lessons_count' => $done,
                'lessons'                 => $unitLessons,
                'electronic_exam'         => $this->presentExam($unit->electronicExam, $attempts),
                'paper_exam'              => $this->presentSheet($unit->paperExam, $submissions),
            ];

            // A unit with no lessons in it yet clears itself, so an empty
            // chapter never wedges the rest of the term shut.
            $previousCleared = $done === count($unitLessons);
        }

        return $tree;
    }

    /**
     * Lessons unlock in order: one is locked while its predecessor is
     * unfinished, and every lesson of a locked unit is locked with it. A free
     * preview is always open — it is the sample the platform sells with.
     *
     * @param  Collection<int, LessonProgress>       $progress
     * @param  Collection<int, WorksheetSubmission>  $submissions
     * @return array<int, array<string, mixed>>
     */
    private function presentLessons(
        CurriculumUnit $unit,
        Collection $progress,
        Collection $submissions,
        bool $unitLocked,
    ): array {
        $previousDone = true;
        $lessons      = [];

        foreach ($unit->lessons as $lesson) {
            $seen        = $progress->get($lesson->id);
            $isCompleted = (bool) ($seen->is_completed ?? false);

            $lessons[] = [
                'id'               => $lesson->id,
                'order'            => $lesson->order,
                'title'            => $lesson->title,
                'description'      => $lesson->description,
                'duration_seconds' => $lesson->duration_seconds,
                'is_free_preview'  => $lesson->is_free_preview,
                // The URL itself is never shipped — the player asks for a
                // signed one — so the page only needs to know there is a video.
                'has_video'        => filled($lesson->video_url),
                'booklet_path'     => $lesson->attachment_path,
                'is_completed'     => $isCompleted,
                'watched_seconds'  => (int) ($seen->watched_seconds ?? 0),
                'is_locked'        => ! $lesson->is_free_preview && ($unitLocked || ! $previousDone),
                'homework'         => $this->presentSheet($lesson->homework, $submissions),
            ];

            $previousDone = $isCompleted;
        }

        return $lessons;
    }

    /**
     * A homework sheet or a paper exam, with this student's own submission.
     *
     * @param  Collection<int, WorksheetSubmission>  $submissions
     * @return array<string, mixed>|null
     */
    private function presentSheet(?Worksheet $sheet, Collection $submissions): ?array
    {
        if (! $sheet) {
            return null;
        }

        $submission = $submissions->get($sheet->id);

        return [
            'id'                  => $sheet->id,
            'title'               => $sheet->title,
            'file_path'           => $sheet->file_path,
            'due_date'            => $sheet->due_date?->format('Y-m-d'),
            'max_score'           => $sheet->max_score,
            'requires_submission' => $sheet->requires_submission,
            'submission'          => $submission === null ? null : [
                'id'               => $submission->id,
                'file_path'        => $submission->submitted_file_path,
                'submitted_at'     => $submission->submitted_at?->toIso8601String(),
                'score'            => $submission->score,
                'teacher_feedback' => $submission->teacher_feedback,
                'graded_at'        => $submission->graded_at?->toIso8601String(),
                'is_graded'        => $submission->isGraded(),
            ],
        ];
    }

    /**
     * "اختبار الوحدة — النموذج الإلكتروني", with its window and the best mark
     * the student has managed so far.
     *
     * @param  Collection<int, Collection<int, QuizAttempt>>  $attempts
     * @return array<string, mixed>|null
     */
    private function presentExam(?Quiz $quiz, Collection $attempts): ?array
    {
        if (! $quiz) {
            return null;
        }

        $mine      = $attempts->get($quiz->id) ?? new Collection();
        $best      = $mine->whereNotNull('submitted_at')->sortByDesc('score')->first();
        $used      = $mine->count();
        $questions = (int) ($quiz->questions_count ?? 0);

        return [
            'id'                 => $quiz->id,
            'title'              => $quiz->title,
            'questions_count'    => $questions,
            'time_limit_minutes' => $quiz->time_limit_minutes,
            'passing_score'      => $quiz->passing_score,
            'is_open'            => $quiz->isOpen(),
            'opens_at'           => $quiz->available_from?->toIso8601String(),
            'closes_at'          => $quiz->available_until?->toIso8601String(),
            'window_label'       => $quiz->windowLabel(),
            'attempts_count'     => $used,
            'attempts_left'      => max(0, Quiz::MAX_QUIZ_ATTEMPTS - $used),
            'best_attempt'       => $best === null ? null : [
                'id'           => $best->id,
                'score'        => $best->score,
                'passed'       => $best->passed,
                'submitted_at' => $best->submitted_at?->toIso8601String(),
            ],
            // An exam with no questions in it cannot be sat, however open it is.
            'can_start' => $quiz->isOpen() && $questions > 0 && $used < Quiz::MAX_QUIZ_ATTEMPTS,
        ];
    }

    // ─── Lookups ──────────────────────────────────────────────────

    /**
     * @param  Collection<int, GroupMaterial>  $lessons
     * @return Collection<int, LessonProgress>
     */
    private function progressMap(User $user, Collection $lessons): Collection
    {
        return LessonProgress::where('student_id', $user->id)
            ->whereIn('lesson_id', $lessons->pluck('id')->all())
            ->get()
            ->keyBy('lesson_id');
    }

    /**
     * @param  Collection<int, CurriculumUnit>  $units
     * @param  Collection<int, GroupMaterial>   $lessons
     * @return Collection<int, WorksheetSubmission>
     */
    private function submissionMap(User $user, Collection $units, Collection $lessons): Collection
    {
        $sheetIds = $lessons->map(fn (GroupMaterial $lesson) => $lesson->homework?->id)
            ->merge($units->map(fn (CurriculumUnit $unit) => $unit->paperExam?->id))
            ->filter()
            ->all();

        return WorksheetSubmission::where('student_id', $user->id)
            ->whereIn('worksheet_id', $sheetIds)
            ->get()
            ->keyBy('worksheet_id');
    }

    /**
     * @param  Collection<int, CurriculumUnit>  $units
     * @return Collection<int, Collection<int, QuizAttempt>>
     */
    private function attemptMap(User $user, Collection $units): Collection
    {
        $quizIds = $units->map(fn (CurriculumUnit $unit) => $unit->electronicExam?->id)->filter()->all();

        return QuizAttempt::where('user_id', $user->id)
            ->whereIn('quiz_id', $quizIds)
            ->get()
            ->groupBy('quiz_id');
    }
}
