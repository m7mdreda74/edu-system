<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Course\Models\Course;
use App\Domain\Enrollment\Models\PurchaseRequest;
use App\Domain\User\Models\ParentStudentLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;

class StudentPurchaseRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
        ]);

        $user = auth()->user();
        $course = Course::findOrFail($request->input('course_id'));

        try {
            // Guard: already enrolled
            if ($user->isEnrolledIn($course)) {
                throw new LogicException('أنت مسجل في هذا الكورس بالفعل.');
            }

            // Guard: check if student has a linked parent
            $parentLink = ParentStudentLink::where('student_user_id', $user->id)->first();
            if (! $parentLink) {
                throw new LogicException('يجب ربط حسابك بحساب ولي الأمر أولاً عن طريق إدخال بريدك الإلكتروني في لوحة تحكم ولي الأمر.');
            }

            // Guard: check if a pending request already exists
            $exists = PurchaseRequest::where('student_user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('status', PurchaseRequest::STATUS_PENDING)
                ->exists();

            if ($exists) {
                throw new LogicException('لقد قمت بالفعل بإرسال طلب شراء معلق لولي أمرك لهذا الكورس.');
            }

            // Create the request
            PurchaseRequest::create([
                'student_user_id' => $user->id,
                'parent_user_id'  => $parentLink->parent_user_id, // link to the first parent
                'course_id'       => $course->id,
                'status'          => PurchaseRequest::STATUS_PENDING,
            ]);

            return back()->with('success', 'تم إرسال طلب الشراء بنجاح إلى ولي أمرك.');
        } catch (LogicException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
