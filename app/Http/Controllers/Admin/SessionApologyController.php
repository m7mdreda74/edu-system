<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Communication\Notifications\TeacherDeductionRecordedNotification;
use App\Domain\Learning\Models\LiveSessionApology;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SessionApologyController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        $apologies = LiveSessionApology::with([
            'teacher:id,name,email,avatar',
            'session:id,title,scheduled_at,teaching_group_id,private_session_slot_id,status',
            'session.teachingGroup:id,name',
            'makeupSession:id,title,scheduled_at,status',
            'resolver:id,name',
            'payout:id,status',
        ])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/SessionApologies', [
            'apologies' => $apologies,
            'filters' => ['status' => $status],
            'stats' => [
                'pending' => LiveSessionApology::where('status', LiveSessionApology::STATUS_PENDING)->count(),
                'makeup' => LiveSessionApology::where('status', LiveSessionApology::STATUS_MAKEUP_SCHEDULED)->count(),
                'deducted' => LiveSessionApology::where('status', LiveSessionApology::STATUS_DEDUCTED)->count(),
                'pending_deductions' => LiveSessionApology::where('status', LiveSessionApology::STATUS_DEDUCTED)
                    ->whereNull('teacher_payout_id')
                    ->sum('deduction_amount'),
            ],
        ]);
    }

    public function deduct(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'amount_qar' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $apology = DB::transaction(function () use ($id, $data): LiveSessionApology {
            $apology = LiveSessionApology::lockForUpdate()->findOrFail($id);

            if ($apology->status !== LiveSessionApology::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'amount_qar' => 'تم حسم هذا الاعتذار بالفعل بحصة تعويضية أو خصم.',
                ]);
            }

            $apology->update([
                'status' => LiveSessionApology::STATUS_DEDUCTED,
                'deduction_amount' => (int) round((float) $data['amount_qar'] * 100),
                'admin_note' => $data['admin_note'] ?? null,
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
            ]);

            return $apology;
        });

        $apology->load(['teacher', 'session:id,title']);
        $apology->teacher?->notify(new TeacherDeductionRecordedNotification($apology));

        return back()->with('success', 'تم تسجيل الخصم وسيُطبّق مرة واحدة على تسوية المعلم التالية.');
    }
}
