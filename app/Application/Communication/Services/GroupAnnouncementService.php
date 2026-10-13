<?php

declare(strict_types=1);

namespace App\Application\Communication\Services;

use App\Domain\Communication\Models\GroupAnnouncement;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GroupAnnouncementService
{
    public function publish(User $teacher, int $groupId, string $title, string $body): GroupAnnouncement
    {
        return DB::transaction(function () use ($teacher, $groupId, $title, $body): GroupAnnouncement {
            $group = TeachingGroup::query()
                ->whereKey($groupId)
                ->where('is_active', true)
                ->whereHas('assignment', fn ($query) => $query->where('teacher_id', $teacher->id))
                ->with(['assignment.subject:id,name'])
                ->firstOrFail();

            $announcement = GroupAnnouncement::create([
                'teacher_id' => $teacher->id,
                'teaching_group_id' => $group->id,
                'title' => $title,
                'body' => $body,
                'published_at' => now(),
            ]);

            $studentIds = Subscription::active()
                ->where('teaching_group_id', $group->id)
                ->pluck('student_id')
                ->unique()
                ->values();

            $notification = fn (string $link): GenericDatabaseNotification => new GenericDatabaseNotification([
                'title' => 'إعلان جديد من المعلم',
                'message' => $group->name.' — '.$title,
                'link' => $link,
            ]);

            User::query()
                ->whereIn('id', $studentIds)
                ->get()
                ->each(fn (User $student) => $student->notify(
                    $notification(route('student.learn', ['groupId' => $group->id])),
                ));

            ParentStudentLink::query()
                ->whereIn('student_user_id', $studentIds)
                ->whereNotNull('verified_at')
                ->with('parent')
                ->get()
                ->groupBy('parent_user_id')
                ->each(function (Collection $links) use ($notification): void {
                    $link = $links->first();
                    $parent = $link?->parent;

                    if ($parent) {
                        $parent->notify($notification(route('parent.dashboard', [
                            'student_id' => $link->student_user_id,
                        ])));
                    }
                });

            return $announcement;
        });
    }

    /**
     * Return only announcements from groups the student currently pays for.
     * The notification is a convenience; this query remains the source of
     * truth if a notification is opened after access has expired.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forStudent(User $student): Collection
    {
        $groupIds = Subscription::active()
            ->where('student_id', $student->id)
            ->whereNotNull('teaching_group_id')
            ->pluck('teaching_group_id');

        return GroupAnnouncement::query()
            ->whereIn('teaching_group_id', $groupIds)
            ->whereNotNull('published_at')
            ->with('group:id,name')
            ->latest('published_at')
            ->limit(20)
            ->get()
            ->map(fn (GroupAnnouncement $announcement): array => $this->present($announcement))
            ->values();
    }

    /** @return array<string, mixed> */
    private function present(GroupAnnouncement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'group' => $announcement->group?->only(['id', 'name']),
            'published_at' => $announcement->published_at?->toIso8601String(),
        ];
    }
}
