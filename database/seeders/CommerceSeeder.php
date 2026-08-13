<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Payment\Models\Coupon;
use App\Domain\Payment\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\TeacherPayout;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Subscriptions and the money behind them.
 *
 * Every state is represented — active, awaiting payment, expired, cancelled —
 * with manual transfers either waiting on an admin or already approved, so the
 * dashboards and the review queue all have something real.
 */
class CommerceSeeder extends Seeder
{
    private const DEFAULT_COMMISSION = 20;

    public function run(): void
    {
        $this->seedCoupons();

        $students = User::role('student')->get();

        foreach ($students as $index => $student) {
            // Subscribe within the student's own grade. Anything else would
            // leave "مواد صفي" showing nothing subscribed, which is the one
            // screen this data exists to demonstrate.
            $groups = $this->groupsForGrade($student->grade_level);

            if ($groups->isEmpty()) {
                continue;
            }

            // Deliberately partial: roughly half their subjects are covered,
            // so both the green and the red badge appear for every student.
            $take = max(1, (int) ceil($groups->count() / 2));

            foreach ($groups->shuffle()->take($take)->values() as $position => $group) {
                $this->seedSubscription($student, $group, $index + $position);
            }
        }

        $this->seedPrivateSubscriptions($students);
        $this->seedPayouts();
    }

    /**
     * The bookable groups in a grade, one per subject.
     *
     * A student has at most one teacher per subject, so offering two groups of
     * the same subject to the same student would produce a state the interface
     * says cannot happen.
     *
     * @return Collection<int, TeachingGroup>
     */
    private function groupsForGrade(?string $gradeKey): Collection
    {
        if (! $gradeKey) {
            return collect();
        }

        $gradeId = GradeLevel::where('key', $gradeKey)->value('id');

        if (! $gradeId) {
            return collect();
        }

        return TeachingGroup::with('assignment')
            ->where('is_active', true)
            ->whereHas('assignment', fn ($q) => $q->where('grade_level_id', $gradeId)->where('is_active', true))
            ->get()
            ->unique(fn (TeachingGroup $group) => $group->assignment?->subject_id)
            ->values();
    }

    private function seedCoupons(): void
    {
        $coupons = [
            ['code' => 'WELCOME10',  'discount_percent' => 10, 'expires_at' => now()->addMonths(3), 'usage_limit' => 200, 'used_count' => 37,  'is_active' => true],
            ['code' => 'RAMADAN25',  'discount_percent' => 25, 'expires_at' => now()->addWeeks(2),  'usage_limit' => 100, 'used_count' => 12,  'is_active' => true],
            ['code' => 'BACK2SCHOOL','discount_percent' => 15, 'expires_at' => now()->subWeek(),    'usage_limit' => 150, 'used_count' => 150, 'is_active' => false],
        ];

        foreach ($coupons as $coupon) {
            Coupon::firstOrCreate(['code' => $coupon['code']], $coupon);
        }
    }

    private function seedSubscription(User $student, TeachingGroup $group, int $seed): void
    {
        // Cycle through the states so every dashboard branch has data.
        $state = match ($seed % 6) {
            0, 1, 2 => Subscription::STATUS_ACTIVE,
            3       => Subscription::STATUS_PENDING,
            4       => Subscription::STATUS_EXPIRED,
            default => Subscription::STATUS_CANCELLED,
        };

        $periodStart = match ($state) {
            Subscription::STATUS_EXPIRED => now()->startOfDay()->subMonths(2),
            default                      => now()->startOfDay()->subDays(random_int(0, 25)),
        };

        $exists = Subscription::where('student_id', $student->id)
            ->where('teaching_group_id', $group->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->exists();

        if ($exists || ! $group->assignment) {
            return;
        }

        $subscription = Subscription::create([
            'student_id'             => $student->id,
            'type'                   => Subscription::TYPE_GROUP,
            'teaching_assignment_id' => $group->teaching_assignment_id,
            'teaching_group_id'      => $group->id,
            'monthly_price'          => $group->monthly_price,
            'currency'               => 'QAR',
            'period_start'           => $periodStart,
            'period_end'             => $periodStart->copy()->addMonth(),
            'status'                 => $state,
            'auto_renew'             => $state === Subscription::STATUS_ACTIVE && $seed % 3 === 0,
            'cancelled_at'           => $state === Subscription::STATUS_CANCELLED ? now()->subDays(5) : null,
        ]);

        // Only a live subscription holds a seat.
        if ($state === Subscription::STATUS_ACTIVE) {
            SessionBooking::firstOrCreate(
                ['student_id' => $student->id, 'teaching_group_id' => $group->id],
                ['status' => 'confirmed', 'booked_at' => $periodStart],
            );
        }

        $this->seedPaymentFor($subscription, $state, $seed);
    }

    private function seedPaymentFor(Subscription $subscription, string $state, int $seed): void
    {
        // Every seeded payment follows the production flow: a Vodafone Cash transfer
        // is either waiting for admin verification or already approved.
        [$status, $gateway] = $state === Subscription::STATUS_PENDING
            ? [Payment::STATUS_PENDING_VERIFICATION, Payment::GATEWAY_VODAFONE_CASH]
            : [Payment::STATUS_PAID, Payment::GATEWAY_VODAFONE_CASH];

        $amount     = $subscription->monthly_price;
        $commission = self::DEFAULT_COMMISSION;
        $teacher    = $subscription->assignment?->teacher;

        if ($teacher?->commission_percent !== null) {
            $commission = $teacher->commission_percent;
        }

        $platformCut = (int) floor(($amount * $commission) / 100);
        $isPaid      = $status === Payment::STATUS_PAID;

        $payment = Payment::create([
            'user_id'                    => $subscription->student_id,
            'subscription_id'            => $subscription->id,
            'amount'                     => $amount,
            'original_amount'            => $amount,
            'currency'                   => 'QAR',
            'gateway'                    => $gateway,
            'gateway_ref'                => 'Vodafone Cash: 01000000000',
            'sender_phone'               => '01000000001',
            'status'                     => $status,
            'paid_at'                    => $isPaid ? $subscription->period_start : null,
            'receipt_path'               => $gateway === Payment::GATEWAY_VODAFONE_CASH ? 'receipts/sample-transfer.jpg' : null,
            'commission_percent'         => $isPaid ? $commission : null,
            'platform_commission_amount' => $isPaid ? $platformCut : null,
            'teacher_earnings'           => $isPaid ? $amount - $platformCut : null,
        ]);

        if ($isPaid) {
            Invoice::create([
                'payment_id'     => $payment->id,
                'invoice_number' => 'INV-' . now()->year . '-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
                'issued_at'      => $subscription->period_start,
            ]);
        }
    }

    /**
     * A few students take private tuition instead of a group.
     *
     * The teacher has to be one their grade could actually book, and on a
     * subject they have not already taken with somebody else — a student with
     * two teachers for one subject is a state the badges cannot express.
     *
     * @param Collection<int, User> $students
     */
    private function seedPrivateSubscriptions(Collection $students): void
    {
        foreach ($students->take(5) as $index => $student) {
            $assignment = $this->privateAssignmentFor($student);

            if (! $assignment) {
                continue;
            }

            $periodStart = now()->startOfDay()->subDays(random_int(1, 15));

            $subscription = Subscription::create([
                'student_id'             => $student->id,
                'type'                   => Subscription::TYPE_PRIVATE,
                'teaching_assignment_id' => $assignment->id,
                'teaching_group_id'      => null,
                'monthly_price'          => $assignment->private_monthly_price,
                'currency'               => 'QAR',
                'period_start'           => $periodStart,
                'period_end'             => $periodStart->copy()->addMonth(),
                'status'                 => Subscription::STATUS_ACTIVE,
            ]);

            $this->seedPaymentFor($subscription, Subscription::STATUS_ACTIVE, $index);

            // Claim one of the teacher's open slots for this student.
            $slot = PrivateSessionSlot::where('teaching_assignment_id', $assignment->id)
                ->where('is_free_intro', false)
                ->where('status', 'available')
                ->orderBy('starts_at')
                ->first();

            if ($slot) {
                $slot->update(['status' => 'booked']);

                SessionBooking::create([
                    'student_id'              => $student->id,
                    'private_session_slot_id' => $slot->id,
                    'status'                  => 'confirmed',
                    'booked_at'               => now()->subDays(2),
                    'notes'                   => 'أرغب في التركيز على مسائل الامتحان الوزاري.',
                ]);
            }
        }
    }

    /** A private teacher in the student's grade, on a subject still open to them. */
    private function privateAssignmentFor(User $student): ?TeachingAssignment
    {
        $gradeId = GradeLevel::where('key', $student->grade_level)->value('id');

        if (! $gradeId) {
            return null;
        }

        $taken = Subscription::where('student_id', $student->id)
            ->with('assignment:id,subject_id')
            ->get()
            ->pluck('assignment.subject_id')
            ->filter()
            ->all();

        return TeachingAssignment::where('grade_level_id', $gradeId)
            ->where('is_active', true)
            ->where('accepts_private', true)
            ->where('private_monthly_price', '>', 0)
            ->whereNotIn('subject_id', $taken)
            ->first();
    }

    /** One settled payout per teacher, plus a pending one to review. */
    private function seedPayouts(): void
    {
        $admin = User::role('admin')->first();

        foreach (User::role('teacher')->get() as $index => $teacher) {
            $earnings = Payment::where('status', Payment::STATUS_PAID)
                ->whereHas('subscription.assignment', fn ($q) => $q->where('teacher_id', $teacher->id))
                ->sum('teacher_earnings');

            if ($earnings <= 0) {
                continue;
            }

            $isPaid = $index % 2 === 0;
            $gross  = (int) ($earnings / (1 - self::DEFAULT_COMMISSION / 100));

            TeacherPayout::create([
                'teacher_id'                 => $teacher->id,
                'amount'                     => (int) $earnings,
                'gross_amount'               => $gross,
                'teacher_earnings'           => (int) $earnings,
                'platform_commission_amount' => $gross - (int) $earnings,
                'platform_commission'        => self::DEFAULT_COMMISSION,
                'period_start'               => Carbon::now()->startOfMonth()->subMonth()->toDateString(),
                'period_end'                 => Carbon::now()->startOfMonth()->subDay()->toDateString(),
                'status'                     => $isPaid ? 'paid' : 'pending',
                'paid_at'                    => $isPaid ? now()->subDays(3) : null,
                'paid_by'                    => $isPaid ? $admin?->id : null,
                'notes'                      => $isPaid ? 'تحويل بنكي — الدفعة الشهرية.' : 'بانتظار المراجعة والتحويل.',
                'teacher_acknowledged_at'    => $isPaid && $index % 4 === 0 ? now()->subDays(2) : null,
            ]);
        }
    }
}
