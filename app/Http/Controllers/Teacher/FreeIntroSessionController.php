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
            'teaching_assignment_id' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $assignment = TeachingAssignment::where('teacher_id', Auth::id())
            ->where('is_active', true)
            ->findOrFail($data['teaching_assignment_id']);

        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);
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
            'timezone' => $data['timezone'] ?? 'Asia/Qatar',
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
        $data = $this->validatedSlot($request);
        $assignment = TeachingAssignment::where('teacher_id', Auth::id())
            ->where('is_active', true)
            ->findOrFail($data['teaching_assignment_id']);

        abort_unless($assignment->offersPrivate(), 422, 'الحصص البرايفيت غير مفعلة لهذا الصف.');

        [$startsAt, $endsAt] = $this->slotTimes($data, 'مدة حصة البرايفيت يجب أن تكون بين 15 دقيقة و8 ساعات.', 480);
        $this->assertNoOverlap($startsAt, $endsAt);

        PrivateSessionSlot::create([
            'teaching_assignment_id' => $assignment->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => $data['timezone'] ?? 'Asia/Qatar',
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
            'teaching_assignment_id' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['nullable', 'timezone'],
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function slotTimes(array $data, string $message, int $maximumMinutes): array
    {
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);
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
