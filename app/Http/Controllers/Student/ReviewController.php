<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\Review;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * ReviewController — Students can rate/review courses they completed.
 * One review per enrollment — enforced at DB level (unique index).
 */
class ReviewController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        $user   = auth()->user();
        $course = Course::where('slug', $slug)->where('is_published', true)->firstOrFail();

        // Guard: must be enrolled
        $enrollment = $user->enrollments()
            ->where('course_id', $course->id)
            ->first();

        abort_unless($enrollment, 403, 'يجب أن تكون مسجلاً في الكورس للتقييم.');

        // Guard: must have completed the course
        abort_unless($enrollment->isCompleted(), 403, 'أكمل الكورس أولاً قبل التقييم.');

        $validated = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        // Upsert: update if exists, create if new
        Review::updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            [
                'rating'      => $validated['rating'],
                'comment'     => $validated['comment'] ?? null,
                'is_approved' => false, // Admin must approve
            ]
        );

        return back()->with('success', 'شكراً! تم إرسال تقييمك وسيُراجع من قِبل الإدارة.');
    }

    public function destroy(string $slug): RedirectResponse
    {
        $user   = auth()->user();
        $course = Course::where('slug', $slug)->firstOrFail();

        Review::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->delete();

        return back()->with('success', 'تم حذف تقييمك.');
    }
}
