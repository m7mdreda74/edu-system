<?php

declare(strict_types=1);

namespace App\Http\Controllers\Parent;

use App\Domain\Subscription\Models\PurchaseRequest;
use App\Domain\User\Models\ParentStudentLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LogicException;

class ParentPurchaseRequestController extends Controller
{
    public function reject(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var \App\Domain\User\Models\User $parent */
        $parent = Auth::user();
        $purchaseRequest = PurchaseRequest::findOrFail($id);

        try {
            // Verify link between parent and student
            $isLinked = ParentStudentLink::where('parent_user_id', $parent->id)
                ->where('student_user_id', $purchaseRequest->student_user_id)
                ->whereNotNull('verified_at')
                ->exists();

            if (! $isLinked) {
                throw new LogicException('غير مصرح لك بإجراء تغييرات على هذا الطلب.');
            }

            if (! $purchaseRequest->isPending()) {
                throw new LogicException('لا يمكن تعديل طلب غير معلق.');
            }

            $purchaseRequest->update([
                'status' => PurchaseRequest::STATUS_REJECTED,
                'notes'  => $validated['notes'] ?? null,
            ]);

            return back()->with('success', 'تم رفض طلب الشراء بنجاح.');
        } catch (LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
