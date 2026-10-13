<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Learning\Services\StudentAssignmentService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function index(StudentAssignmentService $assignments): Response
    {
        $result = $assignments->forStudent(Auth::user());

        return Inertia::render('Student/Assignments', [
            'assignments' => $result['items'],
            'summary' => $result['summary'],
        ]);
    }
}
