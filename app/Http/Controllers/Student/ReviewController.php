<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Learning\Models\TeacherReview;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Students rate the teachers they have studied with. One review per student per
 * teacher — enforced at DB level by a unique index.
 */
class ReviewController extends Controller
{
    public function store(Request $request, int $teacherId): RedirectResponse
    {
        $validated = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $user    = Auth::user();
        $teacher = User::whereHas('roles', fn ($q) => $q->where('name', 'teacher'))->findOrFail($teacherId);

        // You can only rate someone you have actually studied with — any
        // subscription, past or present, counts.
        $hasStudied = Subscription::where('student_id', $user->id)
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_EXPIRED, Subscription::STATUS_CANCELLED])
            ->whereHas('assignment', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->exists();

        abort_unless($hasStudied, 403, 'يمكنك تقييم المعلم بعد الاشتراك معه فقط.');

        TeacherReview::updateOrCreate(
            ['user_id' => $user->id, 'teacher_id' => $teacher->id],
            [
                'rating'      => $validated['rating'],
                'comment'     => $validated['comment'] ?? null,
                'is_approved' => false, // Admin must approve before it shows.
            ],
        );

        return back()->with('success', 'شكراً! تم إرسال تقييمك وسيُراجع من قِبل الإدارة.');
    }

    public function destroy(int $teacherId): RedirectResponse
    {
        TeacherReview::where('user_id', Auth::id())
            ->where('teacher_id', $teacherId)
            ->delete();

        return back()->with('success', 'تم حذف تقييمك.');
    }
}
