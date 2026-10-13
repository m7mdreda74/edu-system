<?php

declare(strict_types=1);

namespace App\Application\Learning\Services;

use App\Domain\Learning\Models\LearningResource;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use App\Services\CurriculumBlobUpload;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResourceLibraryService
{
    private const PRIVATE_PREFIX = 'private://';

    public function __construct(private readonly CurriculumBlobUpload $blobUploads) {}

    /** @return array{resources: LengthAwarePaginator, filters: array<string, string>, categories: list<string>, assignments: list<array<string, mixed>>} */
    public function teacherIndex(User $teacher, array $filters = []): array
    {
        $query = LearningResource::query()
            ->with([
                'owner:id,name',
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

        $resources = $query
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (LearningResource $resource): array => $this->present($resource));

        return [
            'resources' => $resources,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'tag' => $tag,
                'visibility' => $visibility,
            ],
            'categories' => $this->categoriesForTeacher($teacher),
            'assignments' => $this->teacherAssignments($teacher),
        ];
    }

    /** @return array{resources: list<array<string, mixed>>} */
    public function studentIndex(User $student): array
    {
        $assignmentIds = Subscription::active()
            ->where('student_id', $student->id)
            ->whereNotNull('teaching_assignment_id')
            ->pluck('teaching_assignment_id');

        $resources = LearningResource::query()
            ->active()
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

        return ['resources' => $resources];
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
            'approval_status' => LearningResource::APPROVAL_NONE,
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
            $resource->owner_teacher_id === $teacher->id || $resource->isApprovedShared(),
            403,
            'لا يمكن إعادة استخدام هذا المورد قبل اعتماد مشاركته.',
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

        return [
            'resources' => $query->paginate(20)->withQueryString()->through(
                fn (LearningResource $resource): array => $this->present($resource),
            ),
            'filters' => ['status' => $status],
        ];
    }

    public function approve(User $admin, LearningResource $resource): LearningResource
    {
        Gate::forUser($admin)->authorize('moderate', $resource);

        $resource->update([
            'visibility' => LearningResource::VISIBILITY_SHARED,
            'approval_status' => LearningResource::APPROVAL_APPROVED,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
            'rejected_at' => null,
        ]);

        return $resource->fresh();
    }

    public function reject(User $admin, LearningResource $resource): LearningResource
    {
        Gate::forUser($admin)->authorize('moderate', $resource);

        $resource->update([
            'visibility' => LearningResource::VISIBILITY_SHARED,
            'approval_status' => LearningResource::APPROVAL_REJECTED,
            'approved_by_admin_id' => null,
            'approved_at' => null,
            'rejected_at' => now(),
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
            'is_pending_share' => $resource->isPendingShare(),
            'is_approved_shared' => $resource->isApprovedShared(),
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
}
