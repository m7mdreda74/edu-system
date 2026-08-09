<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Domain\Communication\Models\ChatMessage;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Services\CurriculumBlobUpload;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProtectedFileController extends Controller
{
    private const PRIVATE_PREFIX = 'private://';

    public function __construct(
        private readonly CurriculumBlobUpload $blobUploads,
    ) {}

    public function material(GroupMaterial $material): Response
    {
        Gate::authorize('watch', $material);

        return $this->serve($material->attachment_path);
    }

    public function worksheet(Worksheet $worksheet): Response
    {
        $worksheet->load('unit.assignment');
        abort_unless($this->canAccessWorksheet(Auth::user(), $worksheet), 403);

        return $this->serve($worksheet->file_path);
    }

    public function submission(WorksheetSubmission $submission): Response
    {
        $submission->load('worksheet.unit.assignment');
        /** @var User $user */
        $user = Auth::user();

        $isTeacher = $submission->worksheet?->unit?->assignment?->teacher_id === $user->id;
        $isParent = ParentStudentLink::query()
            ->where('parent_user_id', $user->id)
            ->where('student_user_id', $submission->student_id)
            ->whereNotNull('verified_at')
            ->exists();

        abort_unless(
            $user->isAdmin()
                || $isTeacher
                || $submission->student_id === $user->id
                || $isParent,
            403,
        );

        return $this->serve($submission->submitted_file_path);
    }

    public function chatAttachment(ChatMessage $message): Response
    {
        $message->load('conversation');
        abort_unless(
            $message->conversation
                && $message->conversation->participants()
                    ->where('users.id', Auth::id())
                    ->exists(),
            403,
        );

        return $this->serve($message->attachment_path);
    }

    private function canAccessWorksheet(User $user, Worksheet $worksheet): bool
    {
        $assignment = $worksheet->unit?->assignment;

        if (! $assignment) {
            return false;
        }

        if ($user->isAdmin() || $assignment->teacher_id === $user->id) {
            return true;
        }

        if ($user->isStudent()) {
            return $user->hasActiveSubscriptionToAssignment((int) $assignment->id);
        }

        if ($user->isParent()) {
            $studentIds = ParentStudentLink::where('parent_user_id', $user->id)
                ->whereNotNull('verified_at')
                ->pluck('student_user_id');

            return Subscription::active()
                ->whereIn('student_id', $studentIds)
                ->where('teaching_assignment_id', $assignment->id)
                ->exists();
        }

        return false;
    }

    private function serve(?string $storedPath): Response
    {
        abort_unless(filled($storedPath), 404);

        if (str_starts_with($storedPath, 'https://')) {
            return redirect($this->blobUploads->downloadUrlFor($storedPath, (int) Auth::id()));
        }

        if (str_starts_with($storedPath, self::PRIVATE_PREFIX)) {
            $disk = Storage::disk('local');
            $relativePath = substr($storedPath, strlen(self::PRIVATE_PREFIX));
        } elseif (str_starts_with($storedPath, '/storage/')) {
            // Compatibility for records created before private storage was
            // introduced. The route remains authorized, but new uploads never
            // use this public path.
            $disk = Storage::disk('public');
            $relativePath = substr($storedPath, strlen('/storage/'));
        } else {
            abort(404);
        }

        abort_unless($relativePath !== '' && $disk->exists($relativePath), 404);

        $root = realpath($disk->path(''));
        $file = realpath($disk->path($relativePath));

        abort_unless(
            $root
                && $file
                && is_file($file)
                && ($file === $root || str_starts_with($file, $root.DIRECTORY_SEPARATOR)),
            404,
        );

        $mime = mime_content_type($file) ?: 'application/octet-stream';
        $headers = [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];
        $filename = $this->safeFilename($relativePath);

        if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $headers['Content-Disposition'] = 'inline; filename="'.$filename.'"';

            $response = response()->file($file, $headers);
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        }

        $response = response()->download($file, $filename, $headers);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function safeFilename(string $path): string
    {
        $filename = preg_replace('/[^\pL\pN._ -]+/u', '_', basename($path));

        return is_string($filename) && $filename !== '' ? $filename : 'download';
    }
}
