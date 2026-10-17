<?php

declare(strict_types=1);

namespace App\Application\Learning\Services;

use App\Domain\Communication\Notifications\LearningResourceReviewedNotification;
use App\Domain\Learning\Models\LearningResource;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use App\Services\CurriculumBlobUpload;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResourceLibraryService
{
    private const PRIVATE_PREFIX = 'private://';

    public function __construct(private readonly CurriculumBlobUpload $blobUploads) {}

    /** @return array{resources: LengthAwarePaginator, filters: array<string, string>, counts: array<string, int>, categories: list<string>, assignments: list<array<string, mixed>>} */
    public function teacherIndex(User $teacher, array $filters = []): array
    {
        $query = LearningResource::query()
            ->with([
                'owner:id,name',
                'approvedBy:id,name',
                'assignments:id,subject_id,grade_level_id',
                'assignments.subject:id,name',
                'assignments.gradeLevel:id,key,name',
            ])
            ->where('is_active', true)
            ->where(function ($scope) use ($teacher): void {
                $scope
                    ->where('owner_teacher_id', $teacher->id)
                    ->orWhere(function ($shared): void {
                        $shared
                            ->where('visibility', LearningResource::VISIBILITY_SHARED)
                            ->where('approval_status', LearningResource::APPROVAL_APPROVED);
                    });
            });

        $search = trim((string) ($filters['search'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));
        $tag = trim((string) ($filters['tag'] ?? ''));
        $visibility = trim((string) ($filters['visibility'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%');
            });
        }

        if ($category !== '') {
            $query->where('category', $category);
        }

        if ($tag !== '') {
            $query->whereJsonContains('tags', $tag);
        }

        if (in_array($visibility, [LearningResource::VISIBILITY_PRIVATE, LearningResource::VISIBILITY_SHARED], true)) {
            $query->where('visibility', $visibility);
        }

        if (in_array($status, [LearningResource::APPROVAL_PENDING, LearningResource::APPROVAL_APPROVED, LearningResource::APPROVAL_REJECTED], true)) {
            $query->where('approval_status', $status);
        }

        $resources = $query
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (LearningResource $resource): array => $this->present($resource));

        $counts = [
            'total' => LearningResource::where('owner_teacher_id', $teacher->id)->where('is_active', true)->count(),
            'pending' => LearningResource::where('owner_teacher_id', $teacher->id)->where('is_active', true)->where('approval_status', LearningResource::APPROVAL_PENDING)->count(),
            'approved' => LearningResource::where('owner_teacher_id', $teacher->id)->where('is_active', true)->where('approval_status', LearningResource::APPROVAL_APPROVED)->count(),
            'rejected' => LearningResource::where('owner_teacher_id', $teacher->id)->where('is_active', true)->where('approval_status', LearningResource::APPROVAL_REJECTED)->count(),
        ];

        return [
            'resources' => $resources,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'tag' => $tag,
                'visibility' => $visibility,
                'status' => $status,
            ],
            'counts' => $counts,
            'categories' => $this->categoriesForTeacher($teacher),
            'assignments' => $this->teacherAssignments($teacher),
        ];
    }

    /** @return array{resources: list<array<string, mixed>>, subjects: list<array<string, mixed>>} */
    public function studentIndex(User $student): array
    {
        $subscriptions = Subscription::active()
            ->where('student_id', $student->id)
            ->whereNotNull('teaching_assignment_id')
            ->with([
                'assignment.subject:id,name,name_en,icon',
                'assignment.teacher:id,name,avatar,headline',
                'assignment.gradeLevel:id,key,name',
                'assignment.groups:id,name,teaching_assignment_id',
                'assignment.units' => fn ($q) => $q->where(function ($uq) {
                    $uq->where('is_published', true)->orWhereNull('is_published');
                })->orderBy('order')->with([
                    'term:id,name,term_number,year_label',
                    'lessons' => fn ($lq) => $lq->orderBy('order')->with([
                        'activeLessonVideo',
                        'worksheets',
                        'quizzes' => fn ($zq) => $zq->where('is_active', true)->withCount('questions'),
                    ]),
                    'electronicExam' => fn ($eq) => $eq->where('is_active', true)->withCount('questions'),
                    'worksheets' => fn ($wq) => $wq->whereNull('lesson_id'),
                ]),
                'group:id,name,teaching_assignment_id',
            ])
            ->get();

        $assignmentIds = $subscriptions->pluck('teaching_assignment_id')->unique()->values();

        $resources = LearningResource::query()
            ->active()
            ->where('approval_status', LearningResource::APPROVAL_APPROVED)
            ->whereHas('assignments', fn ($query) => $query->whereIn('teaching_assignments.id', $assignmentIds))
            ->with([
                'owner:id,name',
                'assignments:id,subject_id,grade_level_id',
                'assignments.subject:id,name',
                'assignments.gradeLevel:id,key,name',
            ])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (LearningResource $resource): array => $this->present($resource))
            ->values()
            ->all();

        $subjects = $this->buildStudentSubjects($student, $subscriptions, $resources);

        return [
            'resources' => $resources,
            'subjects' => $subjects,
        ];
    }

    public function createLocal(User $teacher, array $data, UploadedFile $file): LearningResource
    {
        $resource = $this->createDraft($teacher, $data + [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        if ((bool) config('services.vercel_blob.serverless', false)) {
            $resource->delete();

            throw ValidationException::withMessages([
                'attachment' => 'ارفع الملف من زر الرفع المباشر بعد تفعيل التخزين الدائم.',
            ]);
        }

        $stored = $file->store('resources/'.$teacher->id, 'local');
        if (! is_string($stored) || $stored === '') {
            $resource->delete();

            throw ValidationException::withMessages([
                'attachment' => 'تعذر حفظ الملف المرفوع.',
            ]);
        }

        $resource->update([
            'file_path' => self::PRIVATE_PREFIX.$stored,
            'is_active' => true,
            'approval_status' => LearningResource::APPROVAL_PENDING,
        ]);

        return $resource->fresh();
    }

    public function createDraft(User $teacher, array $data): LearningResource
    {
        return LearningResource::create([
            'owner_teacher_id' => $teacher->id,
            'title' => trim((string) $data['title']),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'category' => $this->category($data['category'] ?? null),
            'tags' => $this->tags($data['tags'] ?? null),
            'file_path' => null,
            'original_name' => $data['original_name'] ?? null,
            'mime_type' => $data['mime_type'] ?? null,
            'size_bytes' => $data['size_bytes'] ?? null,
            'visibility' => LearningResource::VISIBILITY_PRIVATE,
            'approval_status' => LearningResource::APPROVAL_PENDING,
            'is_active' => false,
        ]);
    }

    public function completeBlobUpload(User $teacher, LearningResource $resource, string $url, string $pathname): LearningResource
    {
        Gate::forUser($teacher)->authorize('update', $resource);
        abort_unless($resource->file_path === null && ! $resource->is_active, 409, 'هذا المورد مكتمل أو غير متاح للرفع.');

        try {
            $stored = $this->blobUploads->validateCompleted(
                $url,
                $pathname,
                (int) $teacher->id,
                CurriculumBlobUpload::KIND_RESOURCE,
                (int) $resource->id,
            );
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'blob_url' => 'تعذر التحقق من الملف المرفوع: '.$exception->getMessage(),
            ]);
        }

        $resource->update([
            'file_path' => $stored,
            'is_active' => true,
            'approval_status' => LearningResource::APPROVAL_PENDING,
        ]);

        return $resource->fresh();
    }

    public function requestShare(User $teacher, LearningResource $resource): LearningResource
    {
        Gate::forUser($teacher)->authorize('requestShare', $resource);

        $resource->update([
            'visibility' => LearningResource::VISIBILITY_SHARED,
            'approval_status' => LearningResource::APPROVAL_PENDING,
            'approved_by_admin_id' => null,
            'approved_at' => null,
            'rejected_at' => null,
        ]);

        return $resource->fresh();
    }

    public function attach(User $teacher, LearningResource $resource, int $assignmentId): void
    {
        Gate::forUser($teacher)->authorize('view', $resource);
        abort_unless(
            $resource->approval_status === LearningResource::APPROVAL_APPROVED,
            403,
            'لا يمكن ربط هذا المورد بالطلاب قبل مراجعة واعتماد الإدارة للملف.',
        );

        $assignment = TeachingAssignment::query()
            ->whereKey($assignmentId)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $resource->assignments()->syncWithoutDetaching([
            $assignment->id => ['attached_by_teacher_id' => $teacher->id],
        ]);
    }

    public function detach(User $teacher, LearningResource $resource, int $assignmentId): void
    {
        $assignment = TeachingAssignment::query()
            ->whereKey($assignmentId)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        abort_unless(
            $resource->owner_teacher_id === $teacher->id
                || ($resource->isApprovedShared() && $resource->assignments()->whereKey($assignment->id)->exists()),
            403,
        );

        $resource->assignments()->detach($assignment->id);
    }

    public function delete(User $teacher, LearningResource $resource): void
    {
        Gate::forUser($teacher)->authorize('delete', $resource);

        $storedPath = $resource->file_path;
        $resource->delete();

        if (is_string($storedPath) && str_starts_with($storedPath, self::PRIVATE_PREFIX)) {
            Storage::disk('local')->delete(substr($storedPath, strlen(self::PRIVATE_PREFIX)));
        }
    }

    public function adminIndex(array $filters = []): array
    {
        $status = trim((string) ($filters['status'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        $query = LearningResource::query()
            ->with([
                'owner:id,name',
                'approvedBy:id,name',
                'assignments:id,subject_id,grade_level_id',
                'assignments.subject:id,name',
                'assignments.gradeLevel:id,key,name',
            ])
            ->latest('id');

        if ($status !== '') {
            $query->where('approval_status', $status);
        }

        if ($search !== '') {
            $query->where(function ($sq) use ($search) {
                $sq->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%')
                    ->orWhereHas('owner', fn ($oq) => $oq->where('name', 'like', '%'.$search.'%'));
            });
        }

        $counts = [
            'total' => LearningResource::count(),
            'pending' => LearningResource::where('approval_status', LearningResource::APPROVAL_PENDING)->count(),
            'approved' => LearningResource::where('approval_status', LearningResource::APPROVAL_APPROVED)->count(),
            'rejected' => LearningResource::where('approval_status', LearningResource::APPROVAL_REJECTED)->count(),
        ];

        return [
            'resources' => $query->paginate(20)->withQueryString()->through(
                fn (LearningResource $resource): array => $this->present($resource),
            ),
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'counts' => $counts,
        ];
    }

    public function approve(User $admin, LearningResource $resource, ?string $adminNotes = null): LearningResource
    {
        Gate::forUser($admin)->authorize('moderate', $resource);

        $resource->update([
            'visibility' => LearningResource::VISIBILITY_SHARED,
            'approval_status' => LearningResource::APPROVAL_APPROVED,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
            'rejected_at' => null,
            'admin_notes' => filled($adminNotes) ? trim($adminNotes) : $resource->admin_notes,
            'rejection_reason' => null,
        ]);

        try {
            $resource->owner?->notify(new LearningResourceReviewedNotification(
                $resource,
                'approved',
                $resource->admin_notes,
                null,
            ));
        } catch (\Throwable) {
        }

        return $resource->fresh();
    }

    public function reject(User $admin, LearningResource $resource, string $rejectionReason, ?string $adminNotes = null): LearningResource
    {
        Gate::forUser($admin)->authorize('moderate', $resource);

        $resource->update([
            'approval_status' => LearningResource::APPROVAL_REJECTED,
            'approved_by_admin_id' => null,
            'approved_at' => null,
            'rejected_at' => now(),
            'rejection_reason' => trim($rejectionReason),
            'admin_notes' => filled($adminNotes) ? trim($adminNotes) : null,
        ]);

        try {
            $resource->owner?->notify(new LearningResourceReviewedNotification(
                $resource,
                'rejected',
                $adminNotes,
                $rejectionReason,
            ));
        } catch (\Throwable) {
        }

        return $resource->fresh();
    }

    public function updateNotes(User $admin, LearningResource $resource, ?string $adminNotes = null, ?string $rejectionReason = null): LearningResource
    {
        Gate::forUser($admin)->authorize('moderate', $resource);

        $resource->update([
            'admin_notes' => filled($adminNotes) ? trim($adminNotes) : null,
            'rejection_reason' => filled($rejectionReason) ? trim($rejectionReason) : $resource->rejection_reason,
        ]);

        return $resource->fresh();
    }

    /** @return list<string> */
    private function categoriesForTeacher(User $teacher): array
    {
        return LearningResource::query()
            ->where('is_active', true)
            ->where(function ($scope) use ($teacher): void {
                $scope
                    ->where('owner_teacher_id', $teacher->id)
                    ->orWhere(function ($shared): void {
                        $shared
                            ->where('visibility', LearningResource::VISIBILITY_SHARED)
                            ->where('approval_status', LearningResource::APPROVAL_APPROVED);
                    });
            })
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values()
            ->all();
    }

    /** @return list<array{id:int, subject:?array<string, mixed>, grade:?array<string, mixed>}> */
    private function teacherAssignments(User $teacher): array
    {
        return TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->with(['subject:id,name', 'gradeLevel:id,key,name'])
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (TeachingAssignment $assignment): array => [
                'id' => $assignment->id,
                'subject' => $assignment->subject?->only(['id', 'name']),
                'grade' => $assignment->gradeLevel?->only(['id', 'key', 'name']),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function present(LearningResource $resource): array
    {
        return [
            'id' => $resource->id,
            'title' => $resource->title,
            'description' => $resource->description,
            'category' => $resource->category,
            'tags' => array_values($resource->tags ?? []),
            'original_name' => $resource->original_name,
            'mime_type' => $resource->mime_type,
            'size_bytes' => $resource->size_bytes,
            'size_label' => $resource->formattedSize(),
            'visibility' => $resource->visibility,
            'approval_status' => $resource->approval_status,
            'is_pending' => $resource->isPending(),
            'is_approved' => $resource->isApproved(),
            'is_rejected' => $resource->isRejected(),
            'is_pending_share' => $resource->isPendingShare(),
            'is_approved_shared' => $resource->isApprovedShared(),
            'admin_notes' => $resource->admin_notes,
            'rejection_reason' => $resource->rejection_reason,
            'approved_by' => $resource->approvedBy?->only(['id', 'name']),
            'approved_at' => $resource->approved_at?->format('Y-m-d H:i'),
            'rejected_at' => $resource->rejected_at?->format('Y-m-d H:i'),
            'created_at' => $resource->created_at?->format('Y-m-d H:i'),
            'created_at_human' => $resource->created_at?->diffForHumans(),
            'owner' => $resource->owner?->only(['id', 'name']),
            'assignments' => $resource->assignments->map(fn (TeachingAssignment $assignment): array => [
                'id' => $assignment->id,
                'subject' => $assignment->subject?->only(['id', 'name']),
                'grade' => $assignment->gradeLevel?->only(['id', 'key', 'name']),
            ])->values()->all(),
            'file_url' => filled($resource->file_path)
                ? route('learning.resource.download', $resource->id)
                : null,
        ];
    }

    /** @return list<string> */
    private function tags(mixed $value): array
    {
        $values = is_array($value)
            ? $value
            : preg_split('/[,،\n]+/u', (string) $value, -1, PREG_SPLIT_NO_EMPTY);

        return collect($values ?: [])
            ->map(fn (mixed $tag): string => trim((string) $tag))
            ->filter()
            ->unique(fn (string $tag): string => mb_strtolower($tag))
            ->take(12)
            ->values()
            ->all();
    }

    private function category(mixed $value): string
    {
        $category = trim((string) $value);

        return Str::limit($category !== '' ? $category : 'عام', 80, '');
    }

    /**
     * @param  Collection<int, Subscription>  $subscriptions
     * @param  list<array<string, mixed>>     $flatResources
     * @return list<array<string, mixed>>
     */
    private function buildStudentSubjects(User $student, Collection $subscriptions, array $flatResources): array
    {
        $subjects = [];

        $resourcesByAssignment = [];
        foreach ($flatResources as $res) {
            foreach ($res['assignments'] ?? [] as $asgn) {
                $resourcesByAssignment[$asgn['id']][] = $res;
            }
        }

        $groupedSubs = $subscriptions->groupBy('teaching_assignment_id');
        $completedLessonIds = LessonProgress::where('student_id', $student->id)
            ->where('is_completed', true)
            ->pluck('lesson_id')
            ->flip();

        foreach ($groupedSubs as $assignmentId => $subs) {
            /** @var Subscription $sub */
            $sub = $subs->first();
            $assignment = $sub->assignment;

            if (! $assignment || ! $assignment->subject) {
                continue;
            }

            $subject = $assignment->subject;
            $teacher = $assignment->teacher;
            $groupId = $sub->teaching_group_id ?? $assignment->groups->first()?->id;

            $unitsData = [];
            $allLessonsFlat = [];

            foreach ($assignment->units as $unit) {
                $unitLessons = [];

                foreach ($unit->lessons as $lesson) {
                    $hasVideo = (bool) (
                        $lesson->activeLessonVideo?->isReady()
                        || filled($lesson->video_url)
                        || filled($lesson->video_path)
                    );

                    $videos = [];
                    if ($hasVideo) {
                        $videos[] = [
                            'id' => $lesson->id,
                            'title' => 'فيديو شرح: ' . $lesson->title,
                            'duration_seconds' => (int) $lesson->duration_seconds,
                            'duration_formatted' => $this->formatDuration((int) $lesson->duration_seconds),
                            'is_free_preview' => (bool) $lesson->is_free_preview,
                            'is_live' => (bool) $lesson->is_live_recording,
                            'stream_url' => route('student.video.url', ['materialId' => $lesson->id]),
                            'learn_url' => $groupId ? route('student.learn', ['groupId' => $groupId]) . '?lesson=' . $lesson->id : null,
                        ];
                    }

                    $booklets = [];
                    if (filled($lesson->attachment_path)) {
                        $ext = strtolower(pathinfo($lesson->attachment_path, PATHINFO_EXTENSION) ?: 'pdf');
                        $isWord = in_array($ext, ['doc', 'docx']);
                        $booklets[] = [
                            'id' => 'mat-' . $lesson->id,
                            'title' => 'ملزمة ومذكرة الدرس',
                            'file_name' => basename($lesson->attachment_path),
                            'extension' => $ext,
                            'file_url' => route('learning.material.download', $lesson->id),
                            'type' => 'booklet',
                            'type_label' => $isWord ? 'ملزمة Word' : 'ملزمة PDF',
                        ];
                    }

                    foreach ($lesson->worksheets as $ws) {
                        if (filled($ws->file_path)) {
                            $ext = strtolower(pathinfo($ws->file_path, PATHINFO_EXTENSION) ?: 'pdf');
                            $isWord = in_array($ext, ['doc', 'docx']);
                            $typeLabel = $ws->type === 'study'
                                ? ($isWord ? 'ملزمة تدريب Word' : 'ملزمة تدريب PDF')
                                : ($isWord ? 'شيت واجب Word' : 'شيت واجب PDF');

                            $booklets[] = [
                                'id' => 'ws-' . $ws->id,
                                'title' => $ws->title,
                                'file_name' => basename($ws->file_path),
                                'extension' => $ext,
                                'file_url' => route('learning.worksheet.download', $ws->id),
                                'type' => $ws->type,
                                'type_label' => $typeLabel,
                                'due_date' => $ws->due_date?->format('Y-m-d'),
                                'requires_submission' => (bool) $ws->requires_submission,
                            ];
                        }
                    }

                    $assessments = [];
                    foreach ($lesson->quizzes as $quiz) {
                        if (! $quiz->is_active) {
                            continue;
                        }

                        $attempt = $quiz->attempts()
                            ->where('user_id', $student->id)
                            ->latest('created_at')
                            ->first();

                        $assessments[] = [
                            'id' => $quiz->id,
                            'title' => $quiz->title,
                            'questions_count' => (int) ($quiz->questions_count ?? $quiz->questions()->count()),
                            'time_limit_minutes' => (int) $quiz->time_limit_minutes,
                            'passing_score' => (int) $quiz->passing_score,
                            'is_open' => (bool) $quiz->isOpen(),
                            'start_url' => route('student.quiz', $quiz->id),
                            'attempts_used' => $quiz->attempts()->where('user_id', $student->id)->count(),
                            'last_score' => $attempt?->score,
                            'passed' => (bool) $attempt?->passed,
                            'submitted_at' => $attempt?->submitted_at?->format('Y-m-d H:i'),
                        ];
                    }

                    $isLessonDone = isset($completedLessonIds[$lesson->id]);

                    $lessonData = [
                        'id' => $lesson->id,
                        'order' => $lesson->order,
                        'title' => $lesson->title,
                        'description' => $lesson->description,
                        'duration_seconds' => (int) $lesson->duration_seconds,
                        'duration_formatted' => $this->formatDuration((int) $lesson->duration_seconds),
                        'unit_id' => $unit->id,
                        'unit_title' => $unit->title,
                        'is_completed' => $isLessonDone,
                        'videos' => $videos,
                        'booklets' => $booklets,
                        'assessments' => $assessments,
                    ];

                    $unitLessons[] = $lessonData;
                    $allLessonsFlat[] = $lessonData;
                }

                $unitExam = null;
                if ($unit->electronicExam && $unit->electronicExam->is_active) {
                    $exam = $unit->electronicExam;
                    $attempt = $exam->attempts()
                        ->where('user_id', $student->id)
                        ->latest('created_at')
                        ->first();

                    $unitExam = [
                        'id' => $exam->id,
                        'title' => 'اختبار إلكتروني شامل — ' . $exam->title,
                        'questions_count' => (int) ($exam->questions_count ?? $exam->questions()->count()),
                        'time_limit_minutes' => (int) $exam->time_limit_minutes,
                        'passing_score' => (int) $exam->passing_score,
                        'is_open' => (bool) $exam->isOpen(),
                        'start_url' => route('student.quiz', $exam->id),
                        'attempts_used' => $exam->attempts()->where('user_id', $student->id)->count(),
                        'last_score' => $attempt?->score,
                        'passed' => (bool) $attempt?->passed,
                    ];
                }

                $unitBooklets = [];
                foreach ($unit->worksheets as $uWs) {
                    if (filled($uWs->file_path)) {
                        $ext = strtolower(pathinfo($uWs->file_path, PATHINFO_EXTENSION) ?: 'pdf');
                        $isWord = in_array($ext, ['doc', 'docx']);
                        $unitBooklets[] = [
                            'id' => 'uws-' . $uWs->id,
                            'title' => $uWs->title,
                            'file_name' => basename($uWs->file_path),
                            'extension' => $ext,
                            'file_url' => route('learning.worksheet.download', $uWs->id),
                            'type' => $uWs->type,
                            'type_label' => $uWs->type === 'paper_exam' ? 'نموذج اختبار ورقي' : ($isWord ? 'ملزمة الوحدة Word' : 'ملزمة الوحدة PDF'),
                        ];
                    }
                }

                $unitsData[] = [
                    'id' => $unit->id,
                    'title' => $unit->title,
                    'description' => $unit->description,
                    'order' => $unit->order,
                    'term_id' => $unit->academic_term_id,
                    'term_name' => $unit->term?->name ?? 'الفصل الدراسي الأول',
                    'term_number' => (int) ($unit->term?->term_number ?? 1),
                    'lessons' => $unitLessons,
                    'unit_exam' => $unitExam,
                    'unit_booklets' => $unitBooklets,
                ];
            }

            $extraResources = $resourcesByAssignment[$assignment->id] ?? [];

            $subjects[] = [
                'id' => $assignment->id,
                'subject_id' => $subject->id,
                'name' => $subject->name,
                'name_en' => $subject->name_en ?? '',
                'icon' => $subject->icon,
                'grade_level_name' => $assignment->gradeLevel?->name,
                'teacher' => [
                    'id' => $teacher?->id,
                    'name' => $teacher?->name,
                    'avatar' => $teacher?->avatar,
                    'headline' => $teacher?->headline,
                ],
                'group_id' => $groupId,
                'units' => $unitsData,
                'lessons_count' => count($allLessonsFlat),
                'total_videos_count' => collect($allLessonsFlat)->sum(fn ($l) => count($l['videos'])),
                'total_booklets_count' => collect($allLessonsFlat)->sum(fn ($l) => count($l['booklets'])),
                'total_assessments_count' => collect($allLessonsFlat)->sum(fn ($l) => count($l['assessments'])),
                'extra_resources' => $extraResources,
            ];
        }

        return $subjects;
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }
        $mins = (int) round($seconds / 60);
        if ($mins < 60) {
            return "{$mins} دقيقة";
        }
        $hours = (int) floor($mins / 60);
        $remMins = $mins % 60;

        return $remMins > 0 ? "{$hours} س و {$remMins} د" : "{$hours} ساعة";
    }
}
