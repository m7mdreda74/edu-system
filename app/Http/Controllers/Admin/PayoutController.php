<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Models\TeacherPayout;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PayoutController extends Controller
{
    public function index(Request $request): Response
    {
        $payouts = TeacherPayout::with('teacher:id,name,email,avatar')
            ->latest()
            ->get();

        $teachers = User::role('teacher')->get(['id', 'name', 'email', 'commission_percent']);
        $balances = Payment::query()
            ->join('courses', 'courses.id', '=', 'payments.course_id')
            ->where('payments.status', Payment::STATUS_PAID)
            ->whereNull('payments.teacher_payout_id')
            ->groupBy('courses.teacher_id')
            ->select('courses.teacher_id', DB::raw('SUM(payments.amount) as gross_amount'), DB::raw('SUM(COALESCE(payments.teacher_earnings, 0)) as teacher_earnings'), DB::raw('SUM(COALESCE(payments.platform_commission_amount, 0)) as platform_commission_amount'))
            ->get()
            ->keyBy('teacher_id');

        return Inertia::render('Admin/Payouts', [
            'payouts' => $payouts,
            'teachers' => $teachers,
            'balances' => $balances,
            'defaultCommission' => (int) (\App\Domain\Settings\Models\PlatformSetting::where('key', 'commission_percent')->value('value') ?? 20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'teacher_id'          => ['required', 'exists:users,id'],
            'period_start'        => ['required', 'date'],
            'period_end'          => ['required', 'date'],
            'notes'               => ['nullable', 'string'],
        ]);

        abort_if($validated['period_end'] < $validated['period_start'], 422, 'نهاية الفترة يجب أن تكون بعد بدايتها.');
        abort_unless(User::findOrFail($validated['teacher_id'])->hasRole('teacher'), 422, 'المستخدم المختار ليس مدرسًا.');

        DB::transaction(function () use ($validated): void {
            $payments = Payment::query()
                ->join('courses', 'courses.id', '=', 'payments.course_id')
                ->where('courses.teacher_id', $validated['teacher_id'])
                ->where('payments.status', Payment::STATUS_PAID)
                ->whereNull('payments.teacher_payout_id')
                ->whereBetween('payments.paid_at', [$validated['period_start'] . ' 00:00:00', $validated['period_end'] . ' 23:59:59'])
                ->select('payments.*')
                ->lockForUpdate()->get();

            abort_if($payments->isEmpty(), 422, 'لا توجد مستحقات غير مُصفّاة لهذا المدرس في الفترة المحددة.');

            $gross = (int) $payments->sum('amount');
            $teacherEarnings = (int) $payments->sum(fn ($payment) => $payment->teacher_earnings ?? 0);
            $platformAmount = (int) $payments->sum(fn ($payment) => $payment->platform_commission_amount ?? 0);

            $payout = TeacherPayout::create([
                'teacher_id' => $validated['teacher_id'],
                'amount' => $teacherEarnings,
                'gross_amount' => $gross,
                'teacher_earnings' => $teacherEarnings,
                'platform_commission_amount' => $platformAmount,
                'platform_commission' => $gross > 0 ? (int) round(($platformAmount / $gross) * 100) : 0,
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);

            Payment::whereIn('id', $payments->pluck('id'))->update(['teacher_payout_id' => $payout->id]);
        });

        return back()->with('success', 'تم تسجيل طلب تسوية الأرباح بنجاح.');
    }

    public function markAsPaid(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'receipt' => ['required', 'file', 'image', 'max:8192'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $payout = TeacherPayout::findOrFail($id);
        abort_if($payout->status === 'paid', 422, 'هذه التصفية تم دفعها بالفعل.');

        $payout->update([
            'status'  => 'paid',
            'paid_at' => now(),
            'receipt_path' => $request->file('receipt')->store('payout-receipts', 'local'),
            'paid_by' => auth()->id(),
            'notes'   => $request->input('notes') ?? $payout->notes,
        ]);

        $payout->teacher?->notify(new \App\Domain\Communication\Notifications\PayoutStatusNotification($payout));

        return back()->with('success', 'تم تأكيد دفع الأرباح للمعلم بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $payout = TeacherPayout::findOrFail($id);
        abort_if($payout->status === 'paid', 403, 'لا يمكن حذف تسوية تم دفعها بالفعل.');

        $payout->delete();

        return back()->with('success', 'تم حذف تسوية الأرباح.');
    }

    public function receipt(int $id)
    {
        $payout = TeacherPayout::findOrFail($id);
        abort_unless($payout->receipt_path, 404);
        return Storage::disk('local')->response($payout->receipt_path);
    }
}
