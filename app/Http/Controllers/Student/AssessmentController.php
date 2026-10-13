<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Quiz\Services\StudentAssessmentService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function index(StudentAssessmentService $assessments): Response
    {
        return Inertia::render('Student/Assessments', $assessments->forStudent(Auth::user()));
    }
}
