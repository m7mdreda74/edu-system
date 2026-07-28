<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\ReorderCurriculumUnitRequest;
use App\Http\Requests\Teacher\StoreCurriculumLessonRequest;
use App\Http\Requests\Teacher\StoreCurriculumSkeletonRequest;
use App\Http\Requests\Teacher\StoreCurriculumUnitRequest;
use App\Http\Requests\Teacher\UpdateCurriculumLessonRequest;
use App\Http\Requests\Teacher\UpdateCurriculumUnitRequest;
use App\Http\Requests\Teacher\UploadBookletRequest;
use App\Http\Requests\Teacher\UploadHomeworkRequest;
use App\Http\Requests\Teacher\UploadPaperExamRequest;
use App\Services\CurriculumBlobUpload;
use App\Support\ArabicOrdinal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The screen where a teacher lays out a whole term: units, the lessons inside
 * them, the explanation video and booklet on each lesson, the homework, and the
 * unit's paper exam.
 *
 * The syllabus hangs off the teaching assignment — teacher × subject × grade —
 * not off a group. A group is a timetable slot and the syllabus is the same
 * whichever slot a student sits in, so the teacher writes it once and both the
 * groups and the private students read from it.
 */
class CurriculumController extends Controller
{
    /** Uploads are stored on the public disk and referenced by this URL prefix. */
    private const PUBLIC_PREFIX = '/storage/';

    public function __construct(
        private readonly CurriculumBlobUpload $blobUploads,
    ) {}

    public function index(Request $request, int $assignmentId): Response
    {
        $assignment = $this->ownedAssignment($assignmentId);
        $assignment->load(['subject:id,name', 'gradeLevel:id,key,name', 'teacher:id,name']);

        $terms = AcademicTerm::orderBy('starts_on')->get();

        // Drives the "this term has content" hint on the tabs, so a teacher who
        // built term two does not open term one and think their work is gone.
        $unitCounts = CurriculumUnit::where('teaching_assignment_id', $assignment->id)
            ->groupBy('academic_term_id')
            ->selectRaw('academic_term_id, count(*) as total')
            ->pluck('total', 'academic_term_id');

        $activeTermId = $terms->firstWhere('id', $request->integer('term'))?->id
            ?? AcademicTerm::currentOrNext()?->id
            ?? $terms->first()?->id;

        $units = $this->unitsFor($assignment, $activeTermId)
            ->map(fn (CurriculumUnit $unit) => $this->presentUnit($unit));

        return Inertia::render('Teacher/Curriculum', [
            'assignment' => [
                'id' => $assignment->id,
                'subject' => $assignment->subject?->only(['id', 'name']),
                'grade' => $assignment->gradeLevel?->only(['id', 'key', 'name']),
                'teacher' => $assignment->teacher?->only(['id', 'name']),
            ],
            'terms' => $terms->map(fn (AcademicTerm $term) => [
                'id' => $term->id,
                'name' => $term->name,
                'year_label' => $term->year_label,
                'term_number' => $term->term_number,
                'full_name' => $term->fullName(),
                'is_current' => (bool) $term->isCurrent(),
                'units_count' => (int) ($unitCounts[$term->id] ?? 0),
            ])->all(),
            'activeTermId' => $activeTermId,
            'units' => $units->all(),
            'stats' => $this->stats($units),
            'directUploads' => [
                'enabled' => $this->blobUploads->enabled(),
                'serverless' => (bool) config('services.vercel_blob.serverless', false),
                'authorize_url' => route('teacher.curriculum-uploads.authorize'),
                'handle_url' => (string) config('services.vercel_blob.handle_url', '/api/blob-upload'),
                'max_bytes' => CurriculumBlobUpload::MAX_BYTES,
            ],
        ]);
    }

    /**
     * Authorize a direct browser-to-Blob upload after checking the teacher owns
     * the curriculum target. The returned token is opaque to the browser and
     * is verified by api/blob-upload.js with APP_KEY.
     */
    public function authorizeBlobUpload(Request $request): JsonResponse
    {
        if (! $this->blobUploads->enabled()) {
            return response()->json([
                'message' => 'تخزين الملفات غير مهيأ في بيئة الإنتاج بعد.',
            ], 503);
        }

        $data = $request->validate([
            'kind' => ['required', 'in:booklet,homework,exam'],
            'target_id' => ['required', 'integer', 'min:1'],
            'pathname' => ['required', 'string', 'max:950'],
            'file_size' => ['required', 'integer', 'min:1', 'max:'.CurriculumBlobUpload::MAX_BYTES],
        ]);

        $kind = (string) $data['kind'];
        $targetId = (int) $data['target_id'];
        $teacher = Auth::id();

        $this->assertUploadTargetOwned($kind, $targetId);

        return response()->json($this->blobUploads->issueAuthorization(
            (int) $teacher,
            $kind,
            $targetId,
            (string) $data['pathname'],
        ));
    }

    /**
     * "المدرس يقدر يحدد عدد وحدات الفصل وعدد الدروس" — generate the shell of a
     * term in one go. It appends: numbering continues from the highest unit
     * already in the term, so running it twice never overwrites existing work.
     */
    public function skeleton(StoreCurriculumSkeletonRequest $request, int $assignmentId): RedirectResponse
    {
        $assignment = $this->ownedAssignment($assignmentId);
        $termId = $request->integer('academic_term_id');
        $unitsCount = $request->integer('units_count');
        $lessonsPerUnit = $request->integer('lessons_per_unit');

        DB::transaction(function () use ($assignment, $termId, $unitsCount, $lessonsPerUnit): void {
            $order = $this->lastUnitOrder($assignment->id, $termId);
            $now = now();
            $lessons = [];

            for ($u = 1; $u <= $unitsCount; $u++) {
                $order++;

                $unit = CurriculumUnit::create([
                    'teaching_assignment_id' => $assignment->id,
                    'academic_term_id' => $termId,
                    'order' => $order,
                    'title' => 'الوحدة '.ArabicOrdinal::feminine($order),
                    'is_published' => true,
                ]);

                for ($l = 1; $l <= $lessonsPerUnit; $l++) {
                    $lessons[] = [
                        'curriculum_unit_id' => $unit->id,
                        'academic_term_id' => $termId,
                        'title' => 'الدرس '.ArabicOrdinal::masculine($l),
                        'order' => $l,
                        'duration_seconds' => 0,
                        'is_free_preview' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // Born inactive: an exam with no questions must not be sittable.
                // The teacher switches it on from the quiz builder.
                Quiz::create([
                    'curriculum_unit_id' => $unit->id,
                    'title' => 'اختبار '.$unit->title,
                    'passing_score' => 60,
                    'is_active' => false,
                ]);
            }

            // Up to 400 rows; one round trip rather than four hundred.
            GroupMaterial::insert($lessons);
        });

        return back()->with('success', "تمت إضافة {$unitsCount} وحدة، في كل وحدة {$lessonsPerUnit} درس.");
    }

    // ─── Units ────────────────────────────────────────────────────

    public function storeUnit(StoreCurriculumUnitRequest $request, int $assignmentId): RedirectResponse
    {
        $assignment = $this->ownedAssignment($assignmentId);
        $termId = $request->integer('academic_term_id');

        CurriculumUnit::create([
            'teaching_assignment_id' => $assignment->id,
            'academic_term_id' => $termId,
            'order' => $this->lastUnitOrder($assignment->id, $termId) + 1,
            'title' => trim((string) $request->input('title')),
            'description' => $request->input('description'),
            'is_published' => $request->boolean('is_published', true),
        ]);

        return back()->with('success', 'تمت إضافة الوحدة.');
    }

    public function updateUnit(UpdateCurriculumUnitRequest $request, int $unitId): RedirectResponse
    {
        $unit = $this->ownedUnit($unitId);
        $data = $request->validated();

        if (isset($data['title'])) {
            $data['title'] = trim($data['title']);
        }

        $unit->update($data);

        return back()->with('success', 'تم حفظ بيانات الوحدة.');
    }

    public function destroyUnit(int $unitId): RedirectResponse
    {
        $unit = $this->ownedUnit($unitId);

        DB::transaction(function () use ($unit): void {
            // The foreign keys cascade the rows away; they cannot reach the
            // uploaded files, so those are collected before the unit goes.
            GroupMaterial::withTrashed()
                ->where('curriculum_unit_id', $unit->id)
                ->pluck('attachment_path')
                ->each(fn (?string $path) => $this->forgetUpload($path));

            Worksheet::where('curriculum_unit_id', $unit->id)
                ->pluck('file_path')
                ->each(fn (?string $path) => $this->forgetUpload($path));

            $unit->delete();
        });

        return back()->with('success', 'تم حذف الوحدة وكل ما بداخلها.');
    }

    /** Swap a unit with its neighbour in the same term. */
    public function reorderUnit(ReorderCurriculumUnitRequest $request, int $unitId): RedirectResponse
    {
        $unit = $this->ownedUnit($unitId);
        $up = $request->input('direction') === 'up';

        $neighbour = CurriculumUnit::where('teaching_assignment_id', $unit->teaching_assignment_id)
            ->where('academic_term_id', $unit->academic_term_id)
            ->where('order', $up ? '<' : '>', $unit->order)
            ->orderBy('order', $up ? 'desc' : 'asc')
            ->first();

        if (! $neighbour) {
            return back();
        }

        DB::transaction(function () use ($unit, $neighbour): void {
            $theirs = $neighbour->order;
            $neighbour->update(['order' => $unit->order]);
            $unit->update(['order' => $theirs]);
        });

        return back()->with('success', 'تم تغيير ترتيب الوحدة.');
    }

    // ─── Lessons ──────────────────────────────────────────────────

    public function storeLesson(StoreCurriculumLessonRequest $request, int $unitId): RedirectResponse
    {
        $unit = $this->ownedUnit($unitId);

        GroupMaterial::create([
            'curriculum_unit_id' => $unit->id,
            'academic_term_id' => $unit->academic_term_id,
            'order' => $this->lastLessonOrder($unit->id) + 1,
            'title' => trim((string) $request->input('title')),
            'video_url' => $request->input('video_url'),
            'duration_seconds' => $request->integer('duration_seconds'),
            'description' => $request->input('description'),
            'is_free_preview' => $request->boolean('is_free_preview'),
        ]);

        return back()->with('success', 'تمت إضافة الدرس.');
    }

    public function updateLesson(UpdateCurriculumLessonRequest $request, int $lessonId): RedirectResponse
    {
        $lesson = $this->ownedLesson($lessonId);
        $data = $request->validated();

        if (isset($data['title'])) {
            $data['title'] = trim($data['title']);
        }

        $lesson->update($data);

        return back()->with('success', 'تم حفظ بيانات الدرس.');
    }

    public function destroyLesson(int $lessonId): RedirectResponse
    {
        $lesson = $this->ownedLesson($lessonId);

        // Soft delete: the row keeps its booklet and homework so a mistaken
        // delete can be undone in the database. Removing the unit sweeps both.
        $lesson->delete();

        return back()->with('success', 'تم حذف الدرس.');
    }

    /** "ملزمة شرح الدرس" — any format, up to 25 MB. */
    public function storeBooklet(UploadBookletRequest $request, int $lessonId): RedirectResponse
    {
        $lesson = $this->ownedLesson($lessonId);

        $oldPath = $lesson->attachment_path;
        $newPath = $this->storeUploadFromRequest(
            $request,
            'booklet',
            'booklets',
            CurriculumBlobUpload::KIND_BOOKLET,
            $lesson->id,
        );

        $lesson->update([
            'attachment_path' => $newPath,
        ]);
        $this->forgetUpload($oldPath);

        return back()->with('success', 'تم رفع ملزمة الدرس.');
    }

    /** "الواجب" — one per lesson; posting again replaces the file in place. */
    public function storeHomework(UploadHomeworkRequest $request, int $lessonId): RedirectResponse
    {
        $lesson = $this->ownedLesson($lessonId);
        $homework = $lesson->homework()->first();
        $oldPath = $homework?->file_path;

        $data = [
            'curriculum_unit_id' => $lesson->curriculum_unit_id,
            'lesson_id' => $lesson->id,
            'type' => Worksheet::TYPE_HOMEWORK,
            ...$this->sheetAttributes($request, $homework, "واجب {$lesson->title}"),
        ];

        $newPath = $this->storeUploadFromRequest(
            $request,
            'file',
            'homework',
            CurriculumBlobUpload::KIND_HOMEWORK,
            $lesson->id,
        );

        if ($newPath !== null) {
            $data['file_path'] = $newPath;
        }

        $homework ? $homework->update($data) : Worksheet::create($data);
        if ($newPath !== null) {
            $this->forgetUpload($oldPath, $newPath);
        }

        return back()->with('success', 'تم حفظ واجب الدرس.');
    }

    // ─── Unit exams ───────────────────────────────────────────────

    /**
     * "اختبار الوحدة — النموذج الورقي". The electronic form lives in the quiz
     * builder; this side is a single file the student prints and answers.
     */
    public function storePaperExam(UploadPaperExamRequest $request, int $unitId): RedirectResponse
    {
        $unit = $this->ownedUnit($unitId);
        $exam = $unit->paperExam()->first();
        $oldPath = $exam?->file_path;

        $data = [
            'curriculum_unit_id' => $unit->id,
            'lesson_id' => null,
            'type' => Worksheet::TYPE_PAPER_EXAM,
            ...$this->sheetAttributes($request, $exam, "اختبار {$unit->title} — النموذج الورقي"),
        ];

        $newPath = $this->storeUploadFromRequest(
            $request,
            'file',
            'exams',
            CurriculumBlobUpload::KIND_EXAM,
            $unit->id,
        );

        if ($newPath !== null) {
            $data['file_path'] = $newPath;
        }

        $exam ? $exam->update($data) : Worksheet::create($data);
        if ($newPath !== null) {
            $this->forgetUpload($oldPath, $newPath);
        }

        return back()->with('success', 'تم حفظ النموذج الورقي لاختبار الوحدة.');
    }

    // ─── Presentation ─────────────────────────────────────────────

    /** @return Collection<int, CurriculumUnit> */
    private function unitsFor(TeachingAssignment $assignment, ?int $termId): Collection
    {
        // No academic calendar seeded yet — nothing can hang off a term.
        if ($termId === null) {
            return (new CurriculumUnit)->newCollection();
        }

        return CurriculumUnit::where('teaching_assignment_id', $assignment->id)
            ->where('academic_term_id', $termId)
            ->with([
                'lessons.homework' => fn ($query) => $query->withCount('submissions'),
                'electronicExam' => fn ($query) => $query->withCount('questions'),
                'paperExam' => fn ($query) => $query->withCount('submissions'),
            ])
            ->orderBy('order')
            ->get();
    }

    private function presentUnit(CurriculumUnit $unit): array
    {
        $lessons = $unit->lessons->map(fn (GroupMaterial $lesson) => $this->presentLesson($lesson));

        return [
            'id' => $unit->id,
            'order' => $unit->order,
            'title' => $unit->title,
            'description' => $unit->description,
            'is_published' => $unit->is_published,
            'academic_term_id' => (int) $unit->academic_term_id,
            'lessons' => $lessons->all(),
            'lessons_count' => $lessons->count(),
            'electronic_exam' => $this->presentQuiz($unit->electronicExam),
            'paper_exam' => $this->presentWorksheet($unit->paperExam),
            // The chips on the collapsed row: a slot is green only when every
            // lesson in the unit has it, so an empty unit is grey across.
            'readiness' => [
                'video' => $lessons->isNotEmpty() && $lessons->every(fn (array $lesson) => $lesson['has_video']),
                'booklet' => $lessons->isNotEmpty() && $lessons->every(fn (array $lesson) => $lesson['has_booklet']),
                'homework' => $lessons->isNotEmpty() && $lessons->every(fn (array $lesson) => $lesson['homework'] !== null),
                'electronic_exam' => $unit->electronicExam !== null,
                'paper_exam' => $unit->paperExam !== null,
            ],
        ];
    }

    private function presentLesson(GroupMaterial $lesson): array
    {
        return [
            'id' => $lesson->id,
            'order' => $lesson->order,
            'title' => $lesson->title,
            'description' => $lesson->description,
            'video_url' => $lesson->video_url,
            'duration_seconds' => $lesson->duration_seconds,
            'is_free_preview' => $lesson->is_free_preview,
            'booklet_path' => $lesson->attachment_path,
            'has_video' => filled($lesson->video_url),
            'has_booklet' => filled($lesson->attachment_path),
            'homework' => $this->presentWorksheet($lesson->homework),
        ];
    }

    private function presentQuiz(?Quiz $quiz): ?array
    {
        if (! $quiz) {
            return null;
        }

        $questions = (int) ($quiz->questions_count ?? 0);

        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'questions_count' => $questions,
            'time_limit_minutes' => $quiz->time_limit_minutes,
            'passing_score' => $quiz->passing_score,
            'is_active' => $quiz->is_active,
            'available_from' => $quiz->available_from?->toIso8601String(),
            'available_until' => $quiz->available_until?->toIso8601String(),
            'window_label' => $quiz->windowLabel(),
            'is_open' => $quiz->isOpen(),
            // A skeleton exam exists but has nothing in it yet.
            'is_ready' => $quiz->is_active && $questions > 0,
        ];
    }

    private function presentWorksheet(?Worksheet $sheet): ?array
    {
        if (! $sheet) {
            return null;
        }

        return [
            'id' => $sheet->id,
            'title' => $sheet->title,
            'file_path' => $sheet->file_path,
            'due_date' => $sheet->due_date?->format('Y-m-d'),
            'max_score' => $sheet->max_score,
            'requires_submission' => $sheet->requires_submission,
            'submissions_count' => (int) ($sheet->submissions_count ?? 0),
        ];
    }

    /** @param  Collection<int, array>  $units */
    private function stats(Collection $units): array
    {
        $lessons = $units->flatMap(fn (array $unit) => $unit['lessons']);

        return [
            'units' => $units->count(),
            'lessons' => $lessons->count(),
            'complete_lessons' => $lessons
                ->filter(fn (array $lesson) => $lesson['has_video'] && $lesson['has_booklet'] && $lesson['homework'] !== null)
                ->count(),
            'ready_exams' => $units
                ->filter(fn (array $unit) => $unit['electronic_exam']['is_ready'] ?? false)
                ->count()
                + $units->filter(fn (array $unit) => $unit['paper_exam'] !== null)->count(),
        ];
    }

    // ─── Internals ────────────────────────────────────────────────

    private function ownedAssignment(int $assignmentId): TeachingAssignment
    {
        $assignment = TeachingAssignment::findOrFail($assignmentId);

        $this->assertOwns($assignment);

        return $assignment;
    }

    private function ownedUnit(int $unitId): CurriculumUnit
    {
        $unit = CurriculumUnit::with('assignment')->findOrFail($unitId);

        $this->assertOwns($unit->assignment);

        return $unit;
    }

    /** Lesson → unit → assignment: ownership always lives at the top. */
    private function ownedLesson(int $lessonId): GroupMaterial
    {
        $lesson = GroupMaterial::with('unit.assignment')->findOrFail($lessonId);

        $this->assertOwns($lesson->unit?->assignment);

        return $lesson;
    }

    private function assertOwns(?TeachingAssignment $assignment): void
    {
        abort_unless(
            $assignment && $assignment->teacher_id === Auth::id(),
            403,
            'هذا التكليف ليس ضمن جدولك.',
        );
    }

    /** Trashed lessons still hold their number, or a new one would collide. */
    private function lastLessonOrder(int $unitId): int
    {
        return (int) GroupMaterial::withTrashed()->where('curriculum_unit_id', $unitId)->max('order');
    }

    private function lastUnitOrder(int $assignmentId, int $termId): int
    {
        return (int) CurriculumUnit::where('teaching_assignment_id', $assignmentId)
            ->where('academic_term_id', $termId)
            ->max('order');
    }

    private function storeUploadFromRequest(
        Request $request,
        string $fileField,
        string $folder,
        string $kind,
        int $targetId,
    ): ?string {
        if ($request->hasFile($fileField)) {
            if ((bool) config('services.vercel_blob.serverless', false)) {
                throw ValidationException::withMessages([
                    $fileField => 'ارفع الملف من زر الرفع مرة أخرى بعد تفعيل التخزين الدائم.',
                ]);
            }

            $stored = $request->file($fileField)->store($folder, 'public');

            if (! is_string($stored) || $stored === '') {
                throw ValidationException::withMessages([
                    $fileField => 'تعذر حفظ الملف المرفوع.',
                ]);
            }

            return self::PUBLIC_PREFIX.$stored;
        }

        if (! $request->filled('blob_url')) {
            return null;
        }

        try {
            return $this->blobUploads->validateCompleted(
                (string) $request->input('blob_url'),
                (string) $request->input('blob_pathname'),
                (int) Auth::id(),
                $kind,
                $targetId,
            );
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'blob_url' => 'تعذر التحقق من الملف المرفوع. أعد رفعه من فضلك.',
            ]);
        }
    }

    private function forgetUpload(?string $publicPath, ?string $replacement = null): void
    {
        if (
            $publicPath === null
            || $publicPath === $replacement
            || ! str_starts_with($publicPath, self::PUBLIC_PREFIX)
        ) {
            return;
        }

        Storage::disk('public')->delete(substr($publicPath, strlen(self::PUBLIC_PREFIX)));
    }

    private function assertUploadTargetOwned(string $kind, int $targetId): void
    {
        match ($kind) {
            CurriculumBlobUpload::KIND_BOOKLET,
            CurriculumBlobUpload::KIND_HOMEWORK => $this->ownedLesson($targetId),
            CurriculumBlobUpload::KIND_EXAM => $this->ownedUnit($targetId),
            default => throw ValidationException::withMessages([
                'kind' => 'نوع الرفع غير صالح.',
            ]),
        };
    }

    private function titleOr(?string $given, string $fallback): string
    {
        return trim((string) $given) ?: $fallback;
    }

    /**
     * Shared shape of a homework sheet and a paper exam.
     *
     * Each slot on the page posts only the field it owns, so a key that is not
     * in the request must leave the stored value alone — moving a due date must
     * not wipe the score that was set from another control.
     */
    private function sheetAttributes(Request $request, ?Worksheet $existing, string $defaultTitle): array
    {
        $data = [];

        if ($existing === null || $request->has('title')) {
            $data['title'] = $this->titleOr($request->input('title'), $existing?->title ?: $defaultTitle);
        }

        if ($existing === null || $request->has('due_date')) {
            $data['due_date'] = $request->input('due_date');
        }

        if ($existing === null || $request->has('max_score')) {
            $data['max_score'] = $request->filled('max_score') ? $request->integer('max_score') : null;
        }

        if ($existing === null || $request->has('requires_submission')) {
            $data['requires_submission'] = $request->boolean('requires_submission', true);
        }

        return $data;
    }
}
