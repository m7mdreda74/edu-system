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
            $request->only(['status']),
        ));
    }

    public function approve(LearningResource $resource, ResourceLibraryService $resources): RedirectResponse
    {
        $resources->approve(Auth::user(), $resource);

        return back()->with('success', 'تم اعتماد المورد ومشاركته مع المعلمين.');
    }

    public function reject(LearningResource $resource, ResourceLibraryService $resources): RedirectResponse
    {
        $resources->reject(Auth::user(), $resource);

        return back()->with('success', 'تم رفض مشاركة المورد.');
    }
}
