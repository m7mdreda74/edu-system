<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Learning\Services\ResourceLibraryService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ResourceLibraryController extends Controller
{
    public function index(ResourceLibraryService $resources): Response
    {
        return Inertia::render('Student/Resources', $resources->studentIndex(Auth::user()));
    }
}
