<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Payment\Models\TeacherPayout;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Storage;

class PayoutController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Teacher/Payouts', [
            'payouts' => TeacherPayout::where('teacher_id', auth()->id())->latest()->get(),
        ]);
    }

    public function acknowledge(Request $request, int $id): RedirectResponse
    {
        $payout = TeacherPayout::where('teacher_id', auth()->id())->findOrFail($id);
        abort_if($payout->status !== 'paid', 422, 'لا يمكن الإقرار قبل تسجيل الدفع من الأدمن.');
        abort_if($payout->teacher_acknowledged_at, 422, 'تم تسجيل إقرار الاستلام مسبقًا.');

        $payout->update([
            'teacher_acknowledged_at' => now(),
            'teacher_acknowledgment_note' => $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null,
        ]);

        foreach (\App\Domain\User\Models\User::role('admin')->get() as $admin) {
            $admin->notify(new \App\Domain\Communication\Notifications\PayoutAcknowledgedNotification($payout));
        }

        return back()->with('success', 'تم تسجيل إقرارك باستلام مستحقاتك.');
    }

    public function receipt(int $id)
    {
        $payout = TeacherPayout::where('teacher_id', auth()->id())->findOrFail($id);
        abort_unless($payout->receipt_path, 404);
        return Storage::disk('local')->response($payout->receipt_path);
    }
}
