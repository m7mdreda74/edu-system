<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class FreeIntroSessionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teaching_assignment_id' => ['required', 'integer', 'min:1', 'exists:teaching_assignments,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'timezone' => ['nullable', 'string', 'timezone'],
        ]);

        $assignment = TeachingAssignment::where('teacher_id', Auth::id())
            ->where('is_active', true)
            ->findOrFail($data['teaching_assignment_id']);

        $timezone = $data['timezone'] ?? 'Asia/Qatar';

        $startsAt = Carbon::parse($data['starts_at'], $timezone)->utc();
        $endsAt = Carbon::parse($data['ends_at'], $timezone)->utc();

        if ($startsAt->isPast()) {
            throw ValidationException::withMessages([
                'starts_at' => 'موعد الحصة التجريبية يجب أن يكون في المستقبل.',
            ]);
        }

        if ($endsAt->lte($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'وقت انتهاء الحصة يجب أن يكون بعد وقت البدء.',
            ]);
        }

        $duration = $startsAt->diffInMinutes($endsAt);

        if ($duration < 15 || $duration > 180) {
            throw ValidationException::withMessages([
                'ends_at' => 'مدة الحصة المجانية يجب أن تكون بين 15 دقيقة و3 ساعات.',
            ]);
        }

        $overlap = PrivateSessionSlot::whereHas(
            'assignment',
            fn ($query) => $query->where('teacher_id', Auth::id()),
        )
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'starts_at' => 'هذا الموعد يتعارض مع حصة فردية أخرى في جدولك.',
            ]);
        }

        PrivateSessionSlot::create([
            'teaching_assignment_id' => $assignment->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => $timezone,
            'is_free_intro' => true,
            'status' => 'available',
        ]);

        return back()->with('success', 'تم نشر موعد الحصة التجريبية المجانية للطلاب.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $slot = PrivateSessionSlot::with(['assignment', 'booking'])->findOrFail($id);

        abort_unless(
            $slot->is_free_intro && $slot->assignment?->teacher_id === Auth::id(),
            403,
        );

        if ($slot->booking?->status === 'confirmed') {
            return back()->with('error', 'لا يمكن إلغاء الحصة المجانية بعد حجزها. تواصل مع الطالب وحدد حصة تعويضية.');
        }

        $slot->update(['status' => 'cancelled']);

        return back()->with('success', 'تم إلغاء الموعد المجاني.');
    }

    public function storePrivate(Request $request): RedirectResponse
    {
        if ($request->has('days')) {
            return app(\App\Http\Controllers\Teacher\TeachingScheduleController::class)->storePrivateGroup($request);
        }

        $data = $this->validatedSlot($request);
        $assignment = TeachingAssignment::where('teacher_id', Auth::id())
            ->where('is_active', true)
            ->findOrFail($data['teaching_assignment_id']);

        abort_unless($assignment->offersPrivate(), 422, 'الحصص البرايفيت غير مفعلة لهذا الصف.');

        [$startsAt, $endsAt] = $this->slotTimes($data, 'مدة حصة البرايفيت يجب أن تكون بين 15 دقيقة و8 ساعات.', 480);
        $this->assertNoOverlap($startsAt, $endsAt);

        $timezone = $data['timezone'] ?? 'Asia/Qatar';

        PrivateSessionSlot::create([
            'teaching_assignment_id' => $assignment->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => $timezone,
            'is_free_intro' => false,
            'status' => 'available',
        ]);

        return back()->with('success', 'تم نشر موعد البرايفيت وأصبح متاحًا لطالب واحد فقط.');
    }

    public function destroyPrivate(int $id): RedirectResponse
    {
        $slot = PrivateSessionSlot::with(['assignment', 'booking'])->findOrFail($id);

        abort_unless(! $slot->is_free_intro && $slot->assignment?->teacher_id === Auth::id(), 403);

        if ($slot->booking?->status === 'confirmed') {
            return back()->with('error', 'لا يمكن إلغاء موعد برايفيت بعد حجزه.');
        }

        $slot->update(['status' => 'cancelled']);

        return back()->with('success', 'تم إلغاء موعد البرايفيت.');
    }

    /** @return array<string, mixed> */
    private function validatedSlot(Request $request): array
    {
        return $request->validate([
            'teaching_assignment_id' => ['required', 'integer', 'min:1', 'exists:teaching_assignments,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'timezone' => ['nullable', 'string', 'timezone'],
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function slotTimes(array $data, string $message, int $maximumMinutes): array
    {
        $timezone = $data['timezone'] ?? 'Asia/Qatar';
        $startsAt = Carbon::parse($data['starts_at'], $timezone)->utc();
        $endsAt = Carbon::parse($data['ends_at'], $timezone)->utc();

        if ($startsAt->isPast()) {
            throw ValidationException::withMessages([
                'starts_at' => 'موعد الحصة يجب أن يكون في المستقبل.',
            ]);
        }

        if ($endsAt->lte($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'وقت انتهاء الحصة يجب أن يكون بعد وقت البدء.',
            ]);
        }

        $duration = $startsAt->diffInMinutes($endsAt);

        if ($duration < 15 || $duration > $maximumMinutes) {
            throw ValidationException::withMessages(['ends_at' => $message]);
        }

        return [$startsAt, $endsAt];
    }

    private function assertNoOverlap(Carbon $startsAt, Carbon $endsAt): void
    {
        $overlap = PrivateSessionSlot::whereHas(
            'assignment',
            fn ($query) => $query->where('teacher_id', Auth::id()),
        )
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'starts_at' => 'هذا الموعد يتعارض مع حصة فردية أخرى في جدولك.',
            ]);
        }
    }
}
