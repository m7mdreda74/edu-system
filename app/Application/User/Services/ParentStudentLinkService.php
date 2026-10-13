<?php

declare(strict_types=1);

namespace App\Application\User\Services;

use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Services\AuditLogger;
use App\Support\PhoneNumber;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ParentStudentLinkService
{
    public function notifyLinkedParents(User $student, Notification $notification): void
    {
        ParentStudentLink::query()
            ->with('parent:id,name')
            ->where('student_user_id', $student->id)
            ->whereNotNull('verified_at')
            ->get()
            ->pluck('parent')
            ->filter()
            ->each(fn (User $parent) => $parent->notify($notification));
    }

    /**
     * Create a pending request without granting parent access.
     *
     * A null result is intentional: the parent endpoint must not reveal
     * whether a phone number belongs to an active student account.
     */
    public function requestLink(
        User $parent,
        string $studentPhone,
        string $relationship = 'guardian',
    ): ?ParentStudentLink {
        $rateKey = "parent-student-link:{$parent->id}";

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            throw ValidationException::withMessages([
                'student_phone' => 'تم تجاوز عدد المحاولات. حاول مرة أخرى بعد قليل.',
            ]);
        }

        RateLimiter::hit($rateKey, 60);

        $student = User::query()
            ->where('phone', PhoneNumber::normalize($studentPhone))
            ->where('is_active', true)
            ->first();

        if (! $student || ! $student->hasRole('student') || $student->is($parent)) {
            return null;
        }

        $notifyStudent = false;
        $link = DB::transaction(function () use ($parent, $student, $relationship, &$notifyStudent): ParentStudentLink {
            $link = ParentStudentLink::query()
                ->where('parent_user_id', $parent->id)
                ->where('student_user_id', $student->id)
                ->lockForUpdate()
                ->first();

            if ($link?->isApproved()) {
                return $link;
            }

            // A retry while the request is still visible is idempotent: keep
            // its expiry and avoid notifying the student repeatedly.
            if ($link?->isPending()) {
                return $link;
            }

            $link ??= new ParentStudentLink([
                'parent_user_id' => $parent->id,
                'student_user_id' => $student->id,
            ]);
            $link->fill([
                'relationship' => $relationship,
                'status' => ParentStudentLink::STATUS_PENDING,
                'requested_by_user_id' => $parent->id,
                'verified_at' => null,
                'expires_at' => now()->addMinutes(30),
                'responded_at' => null,
            ]);
            $link->save();
            $notifyStudent = true;

            AuditLogger::record('parent_student_link.requested', $link, [
                'parent_user_id' => $parent->id,
                'student_user_id' => $student->id,
            ]);

            return $link;
        });

        if ($notifyStudent) {
            $student->notify(new GenericDatabaseNotification([
                'title' => 'طلب ربط حساب ولي أمر',
                'message' => "يرغب {$parent->name} في ربط حسابه بحسابك. راجع الطلب من لوحة الطالب.",
                'link' => route('dashboard'),
            ]));
        }

        return $link;
    }

    /** Explicitly authorized link used by registration/admin account creation. */
    public function linkExistingParent(User $student, string $parentPhone): ParentStudentLink
    {
        $parent = User::query()
            ->where('phone', PhoneNumber::normalize($parentPhone))
            ->where('is_active', true)
            ->first();

        if (! $parent || ! $parent->hasRole('parent')) {
            throw ValidationException::withMessages([
                'parent_phone' => 'لا يمكن التحقق من حساب ولي الأمر. يرجى مراجعة الرقم والمحاولة مرة أخرى.',
            ]);
        }

        return DB::transaction(function () use ($parent, $student): ParentStudentLink {
            $link = ParentStudentLink::query()->firstOrNew([
                'parent_user_id' => $parent->id,
                'student_user_id' => $student->id,
            ]);
            $link->fill([
                'relationship' => 'guardian',
                'status' => ParentStudentLink::STATUS_APPROVED,
                'requested_by_user_id' => $student->id,
                'verified_at' => now(),
                'expires_at' => null,
                'responded_at' => now(),
            ]);
            $link->save();

            AuditLogger::record('parent_student_link.authorized', $link, [
                'parent_user_id' => $parent->id,
                'student_user_id' => $student->id,
                'source' => 'explicit_account_creation',
            ], $student);

            return $link;
        });
    }

    public function approveRequest(User $student, int $linkId): ParentStudentLink
    {
        $expired = false;
        $link = DB::transaction(function () use ($student, $linkId, &$expired): ParentStudentLink {
            $link = ParentStudentLink::query()
                ->with('parent')
                ->whereKey($linkId)
                ->where('student_user_id', $student->id)
                ->whereNull('verified_at')
                ->where('status', ParentStudentLink::STATUS_PENDING)
                ->lockForUpdate()
                ->firstOrFail();

            if ($link->isExpired()) {
                $link->update(['status' => ParentStudentLink::STATUS_EXPIRED]);
                $expired = true;

                return $link->fresh();
            }

            $link->update([
                'status' => ParentStudentLink::STATUS_APPROVED,
                'verified_at' => now(),
                'responded_at' => now(),
            ]);

            AuditLogger::record('parent_student_link.approved', $link, [
                'parent_user_id' => $link->parent_user_id,
                'student_user_id' => $student->id,
            ]);

            $link->parent?->notify(new GenericDatabaseNotification([
                'title' => 'تمت الموافقة على ربط الحساب',
                'message' => "وافق {$student->name} على ربط حسابه بحسابك.",
                'link' => route('parent.dashboard', ['student_id' => $student->id]),
            ]));

            return $link->fresh();
        });

        if ($expired) {
            throw ValidationException::withMessages([
                'link' => 'انتهت صلاحية طلب الربط. اطلب من ولي الأمر إرسال طلب جديد.',
            ]);
        }

        return $link;
    }

    public function rejectRequest(User $student, int $linkId): ParentStudentLink
    {
        return DB::transaction(function () use ($student, $linkId): ParentStudentLink {
            $link = ParentStudentLink::query()
                ->with('parent')
                ->whereKey($linkId)
                ->where('student_user_id', $student->id)
                ->whereNull('verified_at')
                ->where('status', ParentStudentLink::STATUS_PENDING)
                ->lockForUpdate()
                ->firstOrFail();

            $link->update([
                'status' => ParentStudentLink::STATUS_REJECTED,
                'responded_at' => now(),
            ]);

            AuditLogger::record('parent_student_link.rejected', $link, [
                'parent_user_id' => $link->parent_user_id,
                'student_user_id' => $student->id,
            ]);

            $link->parent?->notify(new GenericDatabaseNotification([
                'title' => 'تم رفض طلب ربط الحساب',
                'message' => "رفض {$student->name} طلب ربط الحساب.",
                'link' => route('parent.dashboard'),
            ]));

            return $link->fresh();
        });
    }

    public function revokeByParent(User $parent, int $linkId): ParentStudentLink
    {
        return DB::transaction(function () use ($parent, $linkId): ParentStudentLink {
            $link = ParentStudentLink::query()
                ->whereKey($linkId)
                ->where('parent_user_id', $parent->id)
                ->whereNotNull('verified_at')
                ->lockForUpdate()
                ->firstOrFail();

            $link->update([
                'status' => ParentStudentLink::STATUS_REVOKED,
                'verified_at' => null,
                'responded_at' => now(),
            ]);

            AuditLogger::record('parent_student_link.revoked', $link, [
                'parent_user_id' => $parent->id,
                'student_user_id' => $link->student_user_id,
            ]);

            return $link->fresh();
        });
    }
}
