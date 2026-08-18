<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Learning\Models\GroupMaterial;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * Serves only recordings deliberately published as free previews.
 *
 * The URL is signed when it is placed on a public page. The controller still
 * re-checks the material and its publishing session so a stale URL cannot
 * turn a paid lesson into a public stream.
 */
class FreeRecordingController extends Controller
{
    public function stream(int $materialId): RedirectResponse
    {
        $material = GroupMaterial::with('liveSession')->findOrFail($materialId);
        $session = $material->liveSession;

        abort_unless(
            $material->is_free_preview
            && $session?->is_published_as_lesson
            && filled($material->video_url)
            && $session->recording_url === $material->video_url,
            404,
        );

        return redirect()->away($material->video_url);
    }
}
