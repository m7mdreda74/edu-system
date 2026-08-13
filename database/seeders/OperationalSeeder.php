<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Communication\Notifications\LiveSessionReminderNotification;
use App\Domain\Communication\Notifications\SubscriptionRenewalReminderNotification;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionApology;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the operational states that are difficult to reach from a clean UI:
 * free introductions, reminders, teacher apologies, makeup classes,
 * deductions and notifications.
 */
class OperationalSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFreeIntroBooking();
        $this->seedApologyStates();
        $this->seedLiveSessionReminders();
        $this->seedRenewalReminder();
        $this->seedJitsiDemoRooms();
        $this->seedRoleNotifications();
    }

    private function seedFreeIntroBooking(): void
    {
        $slot = PrivateSessionSlot::query()
            ->with(['assignment.gradeLevel', 'assignment.teacher', 'assignment.subject'])
            ->where('is_free_intro', true)
            ->where('status', 'available')
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->first();

        if (! $slot?->assignment?->gradeLevel) {
            return;
        }

        $student = User::role('student')
            ->where('grade_level', $slot->assignment->gradeLevel->key)
            ->get()
            ->first(function (User $candidate) use ($slot): bool {
                $alreadyUsed = SessionBooking::query()
                    ->where('student_id', $candidate->id)
                    ->where('status', 'confirmed')
                    ->whereHas('privateSlot', fn ($query) => $query
                        ->where('is_free_intro', true)
                        ->whereHas('assignment', fn ($assignment) => $assignment
                            ->where('teacher_id', $slot->assignment->teacher_id)))
                    ->exists();

                $overlap = SessionBooking::query()
                    ->where('student_id', $candidate->id)
                    ->where('status', 'confirmed')
                    ->whereHas('privateSlot', fn ($query) => $query
                        ->where('starts_at', '<', $slot->ends_at)
                        ->where('ends_at', '>', $slot->starts_at))
                    ->exists();

                return ! $alreadyUsed && ! $overlap;
            });

        if (! $student) {
            return;
        }

        SessionBooking::create([
            'student_id'              => $student->id,
            'private_session_slot_id' => $slot->id,
            'status'                  => 'confirmed',
            'booked_at'               => now()->subHours(2),
            'notes'                   => 'حصة تجريبية مجانية للتعرف على أسلوب المعلم.',
        ]);

        $slot->update(['status' => 'booked']);

        $slot->assignment->teacher?->notify(new GenericDatabaseNotification([
            'title'   => 'تم حجز حصة تجريبية مجانية',
            'message' => "{$student->name} حجز الحصة التجريبية في مادة {$slot->assignment->subject?->name}.",
            'link'    => '/teacher/live-sessions',
        ]));

    }

    private function seedApologyStates(): void
    {
        $admin = User::role('admin')->first();
        $sessions = LiveSession::query()
            ->with('teacher')
            ->where('status', LiveSession::STATUS_SCHEDULED)
            ->whereNotNull('teaching_group_id')
            ->orderBy('id')
            ->take(3)
            ->get();

        $definitions = [
            [
                'status' => LiveSessionApology::STATUS_PENDING,
                'reason' => 'ظرف صحي طارئ ولن أستطيع تقديم الحصة في موعدها.',
            ],
            [
                'status' => LiveSessionApology::STATUS_MAKEUP_SCHEDULED,
                'reason' => 'تعذر بدء الحصة بسبب عطل مفاجئ في الاتصال.',
            ],
            [
                'status' => LiveSessionApology::STATUS_DEDUCTED,
                'reason' => 'اعتذار عن الحصة لظرف خارج عن الإرادة دون موعد تعويضي.',
            ],
        ];

        foreach ($definitions as $index => $definition) {
            $session = $sessions->get($index);

            if (! $session || LiveSessionApology::where('live_session_id', $session->id)->exists()) {
                continue;
            }

            $session->update(['status' => LiveSession::STATUS_CANCELLED]);

            $attributes = [
                'live_session_id' => $session->id,
                'teacher_id'      => $session->teacher_id,
                'reason'          => $definition['reason'],
                'status'          => $definition['status'],
            ];

            if ($definition['status'] === LiveSessionApology::STATUS_MAKEUP_SCHEDULED) {
                $scheduledAt = now()->addDays(8)->setTime(19, 0);
                $makeup = LiveSession::create([
                    'teacher_id'        => $session->teacher_id,
                    'teaching_group_id' => $session->teaching_group_id,
                    'title'             => 'حصة تعويضية — '.$session->title,
                    'description'       => 'حصة تعويضية عن الموعد الملغى.',
                    'scheduled_at'      => $scheduledAt,
                    'status'            => LiveSession::STATUS_SCHEDULED,
                ]);

                DB::table('teaching_group_lessons')
                    ->where('live_session_id', $session->id)
                    ->update([
                        'live_session_id' => $makeup->id,
                        'status'          => 'scheduled',
                        'updated_at'      => now(),
                    ]);

                $attributes += [
                    'makeup_session_id'  => $makeup->id,
                    'makeup_scheduled_at' => $scheduledAt,
                    'resolved_at'        => now()->subDay(),
                ];
            }

            if ($definition['status'] === LiveSessionApology::STATUS_DEDUCTED) {
                DB::table('teaching_group_lessons')
                    ->where('live_session_id', $session->id)
                    ->update([
                        'live_session_id' => null,
                        'status'          => 'pending',
                        'updated_at'      => now(),
                    ]);

                $attributes += [
                    'deduction_amount' => 12_500,
                    'admin_note'       => 'خصم تجريبي معلق على التسوية القادمة.',
                    'resolved_by'      => $admin?->id,
                    'resolved_at'      => now()->subHours(6),
                ];
            }

            LiveSessionApology::create($attributes);
        }
    }

    private function seedLiveSessionReminders(): void
    {
        $session = LiveSession::query()
            ->where('status', LiveSession::STATUS_SCHEDULED)
            ->whereNotNull('teaching_group_id')
            ->whereHas('teachingGroup.activeBookings')
            ->orderBy('id')
            ->first();

        if (! $session) {
            return;
        }

        $session->update(['scheduled_at' => now()->addHours(18)]);
        $session->refresh()->load([
            'teachingGroup.assignment.subject',
            'privateSessionSlot.assignment.subject',
        ]);

        $bookings = SessionBooking::query()
            ->where('teaching_group_id', $session->teaching_group_id)
            ->where('status', 'confirmed')
            ->take(3)
            ->get();

        foreach ($bookings as $booking) {
            $inserted = DB::table('live_session_reminders')->insertOrIgnore([
                'live_session_id' => $session->id,
                'student_id'      => $booking->student_id,
                'sent_at'         => now(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            if ($inserted === 1) {
                User::find($booking->student_id)?->notify(new LiveSessionReminderNotification($session));
            }
        }
    }

    private function seedRenewalReminder(): void
    {
        $subscription = Subscription::query()
            ->with(['student', 'assignment.subject', 'assignment.teacher', 'group.schedules'])
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('teaching_group_id')
            ->whereNull('renewal_reminder_sent_at')
            ->orderBy('period_end')
            ->first();

        if (! $subscription || ! $subscription->student) {
            return;
        }

        $lastLessonAt = now()->addDays(2);
        $subscription->update(['renewal_reminder_sent_at' => now()->subMinutes(15)]);
        $subscription->refresh()->load(['student', 'assignment.subject', 'assignment.teacher', 'group.schedules']);

        $notification = new SubscriptionRenewalReminderNotification($subscription, $lastLessonAt);
        $subscription->student->notify($notification);

        ParentStudentLink::query()
            ->with('parent')
            ->where('student_user_id', $subscription->student_id)
            ->whereNotNull('verified_at')
            ->get()
            ->pluck('parent')
            ->filter()
            ->each(fn (User $parent) => $parent->notify(
                new SubscriptionRenewalReminderNotification($subscription, $lastLessonAt),
            ));
    }

    /**
     * Jitsi derives each room name from the live-session ID, so the useful
     * demo state is a real authorized session — not a fake signalling row.
     * The memorable student can open the group room immediately, while the
     * private-session record gives a teacher a second Jitsi scheduling state.
     */
    private function seedJitsiDemoRooms(): void
    {
        $student = User::role('student')
            ->where('email', 'student@altafawwuq.com')
            ->first();

        $subscription = $student
            ? Subscription::active()
                ->where('student_id', $student->id)
                ->whereNotNull('teaching_group_id')
                ->with(['group.assignment.teacher'])
                ->orderBy('id')
                ->first()
            : null;

        $group = $subscription?->group;
        $teacher = $group?->assignment?->teacher;

        if ($student && $group && $teacher) {
            SessionBooking::firstOrCreate(
                ['student_id' => $student->id, 'teaching_group_id' => $group->id],
                ['status' => 'confirmed', 'booked_at' => now()->subDays(2)],
            );

            $session = LiveSession::firstOrCreate(
                [
                    'teaching_group_id' => $group->id,
                    'title' => 'غرفة Jitsi تجريبية — ادخل الآن',
                ],
                [
                    'teacher_id' => $teacher->id,
                    'description' => 'غرفة مباشرة تجريبية جاهزة للحساب student@altafawwuq.com.',
                    'scheduled_at' => now()->subMinutes(20),
                    'started_at' => now()->subMinutes(20),
                    'status' => LiveSession::STATUS_LIVE,
                ],
            );

            LiveSessionAttendee::firstOrCreate(
                ['live_session_id' => $session->id, 'user_id' => $student->id],
                ['joined_at' => now()->subMinutes(16), 'left_at' => null],
            );
        }

        $privateSlot = PrivateSessionSlot::query()
            ->with(['assignment.teacher', 'booking'])
            ->where('is_free_intro', false)
            ->where('status', 'booked')
            ->whereHas('booking', fn ($query) => $query->where('status', 'confirmed'))
            ->orderBy('id')
            ->first();

        if ($privateSlot?->assignment?->teacher && $privateSlot->booking) {
            LiveSession::firstOrCreate(
                ['private_session_slot_id' => $privateSlot->id],
                [
                    'teacher_id' => $privateSlot->assignment->teacher_id,
                    'title' => 'حصة خاصة عبر Jitsi',
                    'description' => 'موعد خاص محجوز وجاهز لفتح غرفة Jitsi من لوحة المعلم.',
                    'scheduled_at' => $privateSlot->starts_at,
                    'status' => LiveSession::STATUS_SCHEDULED,
                ],
            );
        }
    }

    private function seedRoleNotifications(): void
    {
        $admin = User::role('admin')->first();
        $teacher = User::role('teacher')->first();
        $student = User::role('student')->first();
        $parent = User::role('parent')->first();

        $admin?->notify(new GenericDatabaseNotification([
            'title'   => 'تحتاج مراجعة',
            'message' => 'لديك طلبات دفع واعتذارات حصص بانتظار المراجعة.',
            'link'    => '/admin/dashboard',
        ]));

        $teacher?->notify(new GenericDatabaseNotification([
            'title'   => 'ملخص الأسبوع',
            'message' => 'تم تحديث جدول حصصك وبيانات مستحقاتك المالية.',
            'link'    => '/teacher/dashboard',
        ]));

        $student?->notify(new GenericDatabaseNotification([
            'title'   => 'مرحبًا بك في منصة التفوق',
            'message' => 'ابدأ بمشاهدة الدرس المجاني وتابع جدول حصصك القادمة.',
            'link'    => '/dashboard',
        ]));

        $parent?->notify(new GenericDatabaseNotification([
            'title'   => 'تم ربط حسابات الأبناء',
            'message' => 'يمكنك الآن متابعة الحضور والدرجات والاشتراكات من لوحة ولي الأمر.',
            'link'    => '/parent/dashboard',
        ]));

        $admin?->notifications()->oldest()->first()?->markAsRead();
    }

}
