<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Learning\Models\TeacherReview;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Moderation queue for teacher ratings.
 *
 * A review is written unapproved and the public profile only shows approved
 * ones, so without this screen every rating a student writes disappears.
 */
class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,approved'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $status  = $filters['status'] ?? 'pending';

        $reviews = TeacherReview::with(['user:id,name,email,avatar', 'teacher:id,name,avatar'])
            ->when($status === 'pending',  fn ($q) => $q->where('is_approved', false))
            ->when($status === 'approved', fn ($q) => $q->where('is_approved', true))
            ->when(! empty($filters['search']), fn ($q) => $q
                ->where(function ($searchQuery) use ($filters): void {
                    $searchQuery
                        ->whereHas('teacher', fn ($t) => $t->where('name', 'like', '%' . $filters['search'] . '%'))
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%' . $filters['search'] . '%'));
                }))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Reviews', [
            'reviews' => $reviews,
            'filters' => ['status' => $status, 'search' => $filters['search'] ?? ''],
            'counts'  => [
                'pending'  => TeacherReview::where('is_approved', false)->count(),
                'approved' => TeacherReview::where('is_approved', true)->count(),
            ],
        ]);
    }

    public function approve(int $id): RedirectResponse
    {
        TeacherReview::findOrFail($id)->update(['is_approved' => true]);
        $this->forgetTeacherCaches();

        return back()->with('success', 'تم اعتماد التقييم ونشره على صفحة المعلم.');
    }

    public function reject(int $id): RedirectResponse
    {
        TeacherReview::findOrFail($id)->update(['is_approved' => false]);
        $this->forgetTeacherCaches();

        return back()->with('success', 'تم إخفاء التقييم من صفحة المعلم.');
    }

    /** Clearing a backlog one row at a time is nobody's idea of a good time. */
    public function approveAll(): RedirectResponse
    {
        $count = TeacherReview::where('is_approved', false)->update(['is_approved' => true]);
        $this->forgetTeacherCaches();

        return back()->with('success', "تم اعتماد {$count} تقييم.");
    }

    public function destroy(int $id): RedirectResponse
    {
        TeacherReview::findOrFail($id)->delete();
        $this->forgetTeacherCaches();

        return back()->with('success', 'تم حذف التقييم نهائياً.');
    }

    /** Ratings feed the featured-teacher cards on the home page. */
    private function forgetTeacherCaches(): void
    {
        Cache::forget('home.featured_teachers');
    }
}

