<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Learning\Models\LiveSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class RecordedClassController extends Controller
{
    public function destroy(int $id): RedirectResponse
    {
        $session = LiveSession::with('material')->findOrFail($id);

        abort_unless($session->is_published_as_lesson && $session->material, 404);

        DB::transaction(function () use ($session): void {
            $session->material->delete();
            $session->update([
                'recording_url' => null,
                'lesson_id' => null,
                'is_published_as_lesson' => false,
            ]);
        });

        return back()->with('success', 'تم حذف تسجيل الحصة من المنصة.');
    }
}
