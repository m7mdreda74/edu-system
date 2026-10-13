<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Application\Learning\Services\TeacherGradebookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class GradebookController extends Controller
{
    public function index(Request $request, TeacherGradebookService $gradebook): Response
    {
        $assignmentId = $request->integer('assignment') ?: null;
        $result = $gradebook->forTeacher(Auth::user(), $assignmentId);

        return Inertia::render('Teacher/Gradebook', [
            'assignments' => $result['assignments'],
            'rows' => $result['rows'],
            'summary' => $result['summary'],
            'selectedAssignment' => $assignmentId,
        ]);
    }
}
