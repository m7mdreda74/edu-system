<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Learning\Services\ResourceLibraryService;
use App\Domain\Learning\Models\LearningResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ResourceLibraryController extends Controller
{
    public function index(Request $request, ResourceLibraryService $resources): Response
    {
        return Inertia::render('Admin/ResourceLibrary', $resources->adminIndex(
            $request->only(['status', 'search']),
        ));
    }

    public function approve(
        Request $request,
        LearningResource $resource,
        ResourceLibraryService $resources,
    ): RedirectResponse {
        $data = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $resources->approve(Auth::user(), $resource, $data['admin_notes'] ?? null);

        return back()->with('success', 'تم اعتماد المورد ومشاركته بنجاح، مع إشعار المعلم بالملاحظات.');
    }

    public function reject(
        Request $request,
        LearningResource $resource,
        ResourceLibraryService $resources,
    ): RedirectResponse {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3', 'max:2000'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $resources->reject(
            Auth::user(),
            $resource,
            $data['rejection_reason'],
            $data['admin_notes'] ?? null,
        );

        return back()->with('success', 'تم رفض المورد وتوجيه أسباب الرفض والملاحظات للمعلم.');
    }

    public function updateNotes(
        Request $request,
        LearningResource $resource,
        ResourceLibraryService $resources,
    ): RedirectResponse {
        $data = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $resources->updateNotes(
            Auth::user(),
            $resource,
            $data['admin_notes'] ?? null,
            $data['rejection_reason'] ?? null,
        );

        return back()->with('success', 'تم تحديث ملاحظات وتوجيهات الإدارة بنجاح.');
    }
}
