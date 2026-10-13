<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Application\Quiz\Services\QuestionBankService;
use App\Domain\Quiz\Models\QuestionBankQuestion;
use App\Domain\Quiz\Models\Quiz;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\QuestionBankQuestionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class QuestionBankController extends Controller
{
    public function index(Request $request, QuestionBankService $questionBank): Response
    {
        return Inertia::render('Teacher/QuestionBank', $questionBank->index(
            Auth::user(),
            $request->only(['search', 'difficulty', 'type', 'visibility']),
        ));
    }

    public function store(QuestionBankQuestionRequest $request, QuestionBankService $questionBank): RedirectResponse
    {
        $questionBank->create(Auth::user(), $request->bankData());

        return back()->with('success', 'تم حفظ السؤال في بنك الأسئلة.');
    }

    public function update(
        QuestionBankQuestionRequest $request,
        QuestionBankQuestion $question,
        QuestionBankService $questionBank,
    ): RedirectResponse {
        $questionBank->update(Auth::user(), $question, $request->bankData());

        return back()->with('success', 'تم تحديث السؤال في بنك الأسئلة.');
    }

    public function destroy(QuestionBankQuestion $question, QuestionBankService $questionBank): RedirectResponse
    {
        $questionBank->delete(Auth::user(), $question);

        return back()->with('success', 'تم حذف السؤال من بنك الأسئلة.');
    }

    public function attachToQuiz(
        Quiz $quiz,
        QuestionBankQuestion $bankQuestion,
        QuestionBankService $questionBank,
    ): RedirectResponse {
        $questionBank->attachToQuiz(Auth::user(), $quiz, $bankQuestion);

        return back()->with('success', 'تم نسخ السؤال إلى الاختبار مع حفظ نسخته داخله.');
    }
}
