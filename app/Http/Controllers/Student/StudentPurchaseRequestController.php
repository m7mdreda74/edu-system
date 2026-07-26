<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\PurchaseRequest;
use App\Domain\User\Models\ParentStudentLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * A student who cannot pay themselves asks their linked parent to cover a
 * group subscription.
 */
class StudentPurchaseRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'teaching_group_id' => ['required', 'integer', 'exists:teaching_groups,id'],
        ]);

        $user  = Auth::user();
        $group = TeachingGroup::findOrFail($validated['teaching_group_id']);

        try {
            if ($user->hasActiveSubscriptionTo($group)) {
                throw new LogicException('أنت مشترك في هذه المجموعة بالفعل.');
            }

            $parentLink = ParentStudentLink::where('student_user_id', $user->id)->first();

            if (! $parentLink) {
                throw new LogicException('يجب ربط حسابك بحساب ولي الأمر أولاً عن طريق إدخال بريدك الإلكتروني في لوحة تحكم ولي الأمر.');
            }

            $alreadyPending = PurchaseRequest::where('student_user_id', $user->id)
                ->where('teaching_group_id', $group->id)
                ->where('status', PurchaseRequest::STATUS_PENDING)
                ->exists();

            if ($alreadyPending) {
                throw new LogicException('لقد أرسلت بالفعل طلباً معلقاً لولي أمرك لهذه المجموعة.');
            }

            PurchaseRequest::create([
                'student_user_id'   => $user->id,
                'parent_user_id'    => $parentLink->parent_user_id,
                'teaching_group_id' => $group->id,
                'status'            => PurchaseRequest::STATUS_PENDING,
            ]);

            return back()->with('success', 'تم إرسال الطلب بنجاح إلى ولي أمرك.');
        } catch (LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
