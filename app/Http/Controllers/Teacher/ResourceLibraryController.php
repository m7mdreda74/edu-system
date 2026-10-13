<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Application\Learning\Services\ResourceLibraryService;
use App\Domain\Learning\Models\LearningResource;
use App\Http\Controllers\Controller;
use App\Services\CurriculumBlobUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ResourceLibraryController extends Controller
{
    public function index(Request $request, ResourceLibraryService $resources): Response
    {
        return Inertia::render('Teacher/ResourceLibrary', [
            ...$resources->teacherIndex(Auth::user(), $request->only(['search', 'category', 'tag', 'visibility'])),
            'directUploads' => [
                'enabled' => app(CurriculumBlobUpload::class)->enabled(),
                'serverless' => (bool) config('services.vercel_blob.serverless', false),
                'authorize_url' => route('teacher.resource-library.authorize-upload'),
                'handle_url' => (string) config('services.vercel_blob.handle_url', '/api/blob-upload'),
                'max_bytes' => CurriculumBlobUpload::MAX_BYTES,
            ],
        ]);
    }

    public function store(Request $request, ResourceLibraryService $resources): RedirectResponse
    {
        $data = $this->metadata($request);
        $request->validate([
            'attachment' => ['required', 'file', 'mimes:pdf,doc,docx,odt,pptx,zip,png,jpg,jpeg', 'max:25600'],
        ]);

        $resources->createLocal(Auth::user(), $data, $request->file('attachment'));

        return back()->with('success', 'تمت إضافة المورد إلى مكتبتك.');
    }

    public function authorizeUpload(Request $request, ResourceLibraryService $resources): JsonResponse
    {
        $blobUploads = app(CurriculumBlobUpload::class);
        if (! $blobUploads->enabled()) {
            return response()->json([
                'message' => 'اربط Vercel Blob وأضف BLOB_STORE_ID ثم أعد النشر قبل الرفع المباشر.',
            ], 503);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:80'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'file_name' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1', 'max:'.CurriculumBlobUpload::MAX_BYTES],
            'mime_type' => ['required', 'string', 'in:application/msword,application/pdf,application/vnd.oasis.opendocument.text,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/zip,image/jpeg,image/png'],
        ]);

        $resource = $resources->createDraft(Auth::user(), [
            ...$data,
            'original_name' => $data['file_name'],
            'size_bytes' => $data['file_size'],
        ]);
        $extension = strtolower((string) pathinfo($data['file_name'], PATHINFO_EXTENSION));
        $name = Str::slug((string) pathinfo($data['file_name'], PATHINFO_FILENAME)) ?: 'resource';
        $pathname = 'curriculum/'.Auth::id().'/resource/'.$resource->id.'/'.Str::uuid().'-'.$name.'.'.$extension;

        try {
            $authorization = $blobUploads->issueAuthorization(
                (int) Auth::id(),
                CurriculumBlobUpload::KIND_RESOURCE,
                (int) $resource->id,
                $pathname,
            );
        } catch (\Throwable $exception) {
            $resource->delete();

            throw ValidationException::withMessages([
                'attachment' => 'تعذر تجهيز الرفع: '.$exception->getMessage(),
            ]);
        }

        return response()->json([
            'resource_id' => $resource->id,
            ...$authorization,
        ]);
    }

    public function completeUpload(
        Request $request,
        LearningResource $resource,
        ResourceLibraryService $resources,
    ): RedirectResponse {
        $data = $request->validate([
            'blob_url' => ['required', 'url', 'max:2048'],
            'blob_pathname' => ['required', 'string', 'max:950'],
        ]);

        $resources->completeBlobUpload(
            Auth::user(),
            $resource,
            (string) $data['blob_url'],
            (string) $data['blob_pathname'],
        );

        return back()->with('success', 'تم رفع المورد وحفظه في مكتبتك.');
    }

    public function requestShare(LearningResource $resource, ResourceLibraryService $resources): RedirectResponse
    {
        $resources->requestShare(Auth::user(), $resource);

        return back()->with('success', 'تم إرسال المورد للمراجعة قبل مشاركته مع المعلمين.');
    }

    public function attach(Request $request, LearningResource $resource, ResourceLibraryService $resources): RedirectResponse
    {
        $data = $request->validate([
            'assignment_id' => ['required', 'integer', 'min:1'],
        ]);

        $resources->attach(Auth::user(), $resource, (int) $data['assignment_id']);

        return back()->with('success', 'تم ربط المورد بالتكليف ويمكن للطلاب المستحقين الوصول إليه.');
    }

    public function detach(LearningResource $resource, int $assignmentId, ResourceLibraryService $resources): RedirectResponse
    {
        $resources->detach(Auth::user(), $resource, $assignmentId);

        return back()->with('success', 'تم إلغاء ربط المورد بالتكليف.');
    }

    public function destroy(LearningResource $resource, ResourceLibraryService $resources): RedirectResponse
    {
        $resources->delete(Auth::user(), $resource);

        return back()->with('success', 'تم حذف المورد من مكتبتك.');
    }

    /** @return array<string, mixed> */
    private function metadata(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:80'],
            'tags' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
