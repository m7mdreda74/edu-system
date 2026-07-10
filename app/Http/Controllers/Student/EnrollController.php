<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Course\Models\Course;
use App\Domain\Enrollment\Contracts\EnrollmentServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use LogicException;

class EnrollController extends Controller
{
    public function __construct(
        private readonly EnrollmentServiceInterface $enrollmentService,
    ) {}

    public function store(string $slug): RedirectResponse
    {
        $user   = auth()->user();
        $course = Course::where('slug', $slug)->where('is_published', true)->firstOrFail();

        try {
            $this->enrollmentService->enrollFree($user, $course);

            return redirect()
                ->route('student.learn', ['slug' => $slug])
                ->with('success', "تم تسجيلك في كورس {$course->title} بنجاح!");
        } catch (LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
