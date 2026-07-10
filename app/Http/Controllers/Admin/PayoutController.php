<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Models\TeacherPayout;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    public function index(Request $request): Response
    {
        $payouts = TeacherPayout::with('teacher:id,name,email,avatar')
            ->latest()
            ->get();

        return Inertia::render('Admin/Payouts', [
            'payouts' => $payouts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'teacher_id'          => ['required', 'exists:users,id'],
            'amount'              => ['required', 'integer', 'min:1'], // In halala
            'platform_commission' => ['required', 'integer', 'min:0'],
            'period_start'        => ['required', 'date'],
            'period_end'          => ['required', 'date'],
            'notes'               => ['nullable', 'string'],
        ]);

        $validated['status'] = 'pending';

        TeacherPayout::create($validated);

        return back()->with('success', 'تم تسجيل طلب تسوية الأرباح بنجاح.');
    }

    public function markAsPaid(Request $request, int $id): RedirectResponse
    {
        $payout = TeacherPayout::findOrFail($id);

        $payout->update([
            'status'  => 'paid',
            'paid_at' => now(),
            'notes'   => $request->input('notes') ?? $payout->notes,
        ]);

        return back()->with('success', 'تم تأكيد دفع الأرباح للمعلم بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $payout = TeacherPayout::findOrFail($id);
        abort_if($payout->status === 'paid', 403, 'لا يمكن حذف تسوية تم دفعها بالفعل.');

        $payout->delete();

        return back()->with('success', 'تم حذف تسوية الأرباح.');
    }
}
