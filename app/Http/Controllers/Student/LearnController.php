<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Certificate\Services\CertificateService;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
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
 * The study room for one teaching group: its materials, the student's progress
 * through them, and the homework attached to them.
 */
class LearnController extends Controller
{
    /** A material counts as done once this much of it has been watched. */
    private const COMPLETION_RATIO = 0.9;

    public function __construct(
        private readonly CertificateService $certificates,
    ) {}

    public function show(int $groupId): Response
    {
        /** @var User $user */
        $user  = Auth::user();
        $group = TeachingGroup::with(['assignment.teacher:id,name', 'assignment.subject:id,name,icon'])
            ->findOrFail($groupId);

        $this->authorizeAccess($user, $group);

        return Inertia::render('Student/Learn', [
            'group' => [
                'id'      => $group->id,
                'name'    => $group->name,
                'subject' => $group->assignment?->subject?->only(['id', 'name', 'icon']),
                'teacher' => $group->assignment?->teacher?->only(['id', 'name']),
            ],
            'materials'  => $this->presentMaterials($group, $this->progressMap($user, $group)),
            'progress'   => [
                'percent'           => $this->certificates->progressPercent($user, $group),
                'certificate_ready' => $this->certificates->isEligible($user, $group),
            ],
            'worksheets' => Worksheet::where('teaching_group_id', $group->id)
                ->with(['submissions' => fn ($q) => $q->where('student_id', $user->id)])
                ->get(),
        ]);
    }

    /**
     * AJAX endpoint: update how far the student has watched.
     * Completion is decided server-side from the material's own duration —
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

        $material = GroupMaterial::where('teaching_group_id', $group->id)->findOrFail($materialId);

        $existing    = LessonProgress::where('student_id', $user->id)
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
            'materials'         => $this->presentMaterials($group, $this->progressMap($user, $group)),
        ]);
    }

    public function submitHomework(Request $request, int $groupId, int $worksheetId): RedirectResponse
    {
        $request->validate([
            'submitted_file' => ['required', 'file', 'mimes:pdf,docx,png,jpg,jpeg', 'max:10240'],
        ]);

        /** @var User $user */
        $user  = Auth::user();
        $group = TeachingGroup::findOrFail($groupId);

        $this->authorizeAccess($user, $group);

        $worksheet = Worksheet::where('teaching_group_id', $group->id)->findOrFail($worksheetId);

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

        return back()->with('success', 'تم تسليم الواجب بنجاح.');
    }

    // ─── Internals ────────────────────────────────────────────────

    /** The group's own teacher and admins can always preview the room. */
    private function hasAccess(User $user, TeachingGroup $group): bool
    {
        $group->loadMissing('assignment');

        return $user->isAdmin()
            || $user->id === $group->assignment?->teacher_id
            || $user->hasActiveSubscriptionTo($group);
    }

    private function authorizeAccess(User $user, TeachingGroup $group): void
    {
        abort_unless($this->hasAccess($user, $group), 403, 'يجب أن يكون لديك اشتراك فعّال في هذه المجموعة.');
    }

    private function progressMap(User $user, TeachingGroup $group): Collection
    {
        return LessonProgress::where('student_id', $user->id)
            ->whereIn('lesson_id', $group->materials()->select('group_materials.id'))
            ->get()
            ->keyBy('lesson_id');
    }

    /**
     * Materials unlock in order: one is locked when its predecessor is not
     * finished. Free previews are always open.
     */
    private function presentMaterials(TeachingGroup $group, Collection $progressMap): Collection
    {
        $previousCompleted = true;

        return $group->materials()->get()->map(function (GroupMaterial $material) use ($progressMap, &$previousCompleted) {
            $progress    = $progressMap->get($material->id);
            $isCompleted = (bool) ($progress->is_completed ?? false);
            $isLocked    = ! $previousCompleted && ! $material->is_free_preview && $material->order > 1;

            $previousCompleted = $isCompleted;

            return [
                'id'               => $material->id,
                'title'            => $material->title,
                'description'      => $material->description,
                'duration_seconds' => $material->duration_seconds,
                'order'            => $material->order,
                'is_free_preview'  => $material->is_free_preview,
                'attachment_path'  => $material->attachment_path,
                'is_completed'     => $isCompleted,
                'watched_seconds'  => (int) ($progress->watched_seconds ?? 0),
                'is_locked'        => $isLocked,
            ];
        });
    }
}
