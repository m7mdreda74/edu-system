<?php

declare(strict_types=1);

namespace App\Http\Controllers\Parent;

use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParentDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $parent = auth()->user();

        // Get linked students
        $links = ParentStudentLink::where('parent_user_id', $parent->id)
            ->with(['student:id,name,email,grade_level'])
            ->get();

        $selectedStudentId = $request->input('student_id') 
            ? (int) $request->input('student_id') 
            : ($links->first()?->student_user_id ?? null);

        $studentData = null;
        if ($selectedStudentId) {
            // Check authorization
            $isLinked = $links->contains('student_user_id', $selectedStudentId);
            if ($isLinked) {
                $student = User::find($selectedStudentId);
                
                $enrollments = Enrollment::where('user_id', $selectedStudentId)
                    ->with(['course:id,title,teacher_id', 'course.teacher:id,name'])
                    ->get();

                $payments = Payment::where('user_id', $selectedStudentId)
                    ->with('course:id,title')
                    ->latest()
                    ->get();

                $quizAttempts = \App\Domain\Quiz\Models\QuizAttempt::where('user_id', $selectedStudentId)
                    ->with(['quiz:id,title,passing_score'])
                    ->latest()
                    ->get();

                $studentData = [
                    'student'      => $student,
                    'enrollments'  => $enrollments,
                    'payments'     => $payments,
                    'quizAttempts' => $quizAttempts,
                ];
            }
        }

        return Inertia::render('Parent/Dashboard', [
            'links'          => $links,
            'selectedStudent'=> $studentData,
        ]);
    }

    public function linkStudent(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email'        => ['required', 'email', 'exists:users,email'],
            'relationship' => ['required', 'string', 'in:father,mother,guardian'],
        ]);

        $student = User::where('email', $validated['email'])->firstOrFail();
        
        if (! $student->isStudent()) {
            return back()->with('error', 'البريد الإلكتروني المدخل لا يخص حساب طالب.');
        }

        ParentStudentLink::firstOrCreate(
            [
                'parent_user_id'  => auth()->id(),
                'student_user_id' => $student->id,
            ],
            [
                'relationship' => $validated['relationship'],
                'verified_at'  => now(),
            ]
        );

        return back()->with('success', 'تم ربط حساب الابن/الابنة بنجاح.');
    }

    public function unlinkStudent(int $linkId): RedirectResponse
    {
        $link = ParentStudentLink::where('parent_user_id', auth()->id())->findOrFail($linkId);
        $link->delete();

        return back()->with('success', 'تم إلغاء ربط الحساب بنجاح.');
    }
}
