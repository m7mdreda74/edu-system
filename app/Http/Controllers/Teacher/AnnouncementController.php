<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Application\Communication\Services\GroupAnnouncementService;
use App\Domain\Communication\Models\GroupAnnouncement;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        $teacherId = (int) Auth::id();

        $groups = TeachingGroup::query()
            ->where('is_active', true)
            ->whereHas('assignment', fn ($query) => $query->where('teacher_id', $teacherId))
            ->with([
                'assignment.subject:id,name',
                'announcements' => fn ($query) => $query->latest('published_at')->limit(10),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (TeachingGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'subject' => $group->assignment?->subject?->only(['id', 'name']),
                'announcements' => $group->announcements->map(fn (GroupAnnouncement $announcement): array => [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'body' => $announcement->body,
                    'published_at' => $announcement->published_at?->toIso8601String(),
                ])->values(),
            ])->values();

        return Inertia::render('Teacher/Announcements', [
            'groups' => $groups,
        ]);
    }

    public function store(Request $request, GroupAnnouncementService $announcements): RedirectResponse
    {
        $validated = $request->validate([
            'teaching_group_id' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $announcements->publish(
            Auth::user(),
            (int) $validated['teaching_group_id'],
            trim($validated['title']),
            trim($validated['body']),
        );

        return back()->with('success', 'تم نشر الإعلان وإرسال الإشعار للمستلمين المؤهلين.');
    }
}
