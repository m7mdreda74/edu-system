<?php

declare(strict_types=1);

namespace App\Http\Controllers\Parent;

use App\Domain\Payment\Models\Payment;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Subscription\Models\PurchaseRequest;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ParentDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $parent = Auth::user();

        $links = ParentStudentLink::where('parent_user_id', $parent->id)
            ->with(['student:id,name,email,grade_level'])
            ->get();

        $selectedStudentId = $request->input('student_id')
            ? (int) $request->input('student_id')
            : ($links->first()?->student_user_id ?? null);

        $studentData = null;

        // Only expose a student this parent is actually linked to.
        if ($selectedStudentId && $links->contains('student_user_id', $selectedStudentId)) {
            $studentData = [
                'student'       => User::find($selectedStudentId),
                'subscriptions' => Subscription::where('student_id', $selectedStudentId)
                    ->with([
                        'assignment.subject:id,name,icon',
                        'assignment.teacher:id,name,avatar',
                        'group:id,name',
                    ])
                    ->latest('period_end')
                    ->get()
                    ->map(fn (Subscription $s) => [
                        'id'             => $s->id,
                        'type'           => $s->type,
                        'status'         => $s->status,
                        'label'          => $s->label(),
                        'monthly_price'  => $s->monthly_price,
                        'currency'       => $s->currency,
                        'period_end'     => $s->period_end?->toDateString(),
                        'days_remaining' => $s->daysRemaining(),
                        'subject'        => $s->assignment?->subject?->only(['id', 'name', 'icon']),
                        'teacher'        => $s->assignment?->teacher?->only(['id', 'name', 'avatar']),
                        'group'          => $s->group?->only(['id', 'name']),
                    ])->values(),
                'payments'      => Payment::where('user_id', $selectedStudentId)
                    ->with('subscription.assignment.subject:id,name')
                    ->latest()
                    ->get(),
                'quizAttempts'  => QuizAttempt::where('user_id', $selectedStudentId)
                    ->with(['quiz:id,title,passing_score'])
                    ->latest()
                    ->get(),
            ];
        }

        $pendingRequests = PurchaseRequest::whereIn('student_user_id', $links->pluck('student_user_id'))
            ->where('status', PurchaseRequest::STATUS_PENDING)
            ->with([
                'student:id,name,email',
                'group:id,name,monthly_price,currency,teaching_assignment_id',
                'group.assignment.subject:id,name',
                'group.assignment.teacher:id,name',
            ])
            ->latest()
            ->get();

        return Inertia::render('Parent/Dashboard', [
            'links'           => $links,
            'selectedStudent' => $studentData,
            'pendingRequests' => $pendingRequests,
        ]);
    }

    public function linkStudent(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email'        => ['required', 'email', 'exists:users,email'],
            'relationship' => ['required', 'string', 'in:father,mother,guardian'],
        ]);

        $student = User::where('email', $validated['email'])->firstOrFail();

        if (! $student->isStudent()) {
            return back()->with('error', 'البريد الإلكتروني المدخل لا يخص حساب طالب.');
        }

        ParentStudentLink::firstOrCreate(
            [
                'parent_user_id'  => Auth::id(),
                'student_user_id' => $student->id,
            ],
            [
                'relationship' => $validated['relationship'],
                'verified_at'  => now(),
            ],
        );

        return back()->with('success', 'تم ربط حساب الابن/الابنة بنجاح.');
    }

    public function unlinkStudent(int $linkId): RedirectResponse
    {
        ParentStudentLink::where('parent_user_id', Auth::id())->findOrFail($linkId)->delete();

        return back()->with('success', 'تم إلغاء ربط الحساب بنجاح.');
    }

    /**
     * A parent paying for their child: open the subscription in the child's
     * name, then hand off to checkout.
     */
    public function payForRequest(int $requestId, \App\Application\Subscription\Services\SubscriptionService $subscriptions): RedirectResponse
    {
        $purchaseRequest = PurchaseRequest::with('group')->findOrFail($requestId);

        $isLinked = ParentStudentLink::where('parent_user_id', Auth::id())
            ->where('student_user_id', $purchaseRequest->student_user_id)
            ->exists();

        abort_unless($isLinked, 403, 'غير مصرح لك بالدفع لهذا الطلب.');
        abort_unless($purchaseRequest->group, 404, 'المجموعة المطلوبة لم تعد متاحة.');

        try {
            $subscription = $subscriptions->openForGroup(
                User::findOrFail($purchaseRequest->student_user_id),
                $purchaseRequest->group,
            );
        } catch (\LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('checkout.show', $subscription->id);
    }
}
