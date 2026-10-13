<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\User\Services\ParentStudentLinkService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ParentStudentLinkController extends Controller
{
    public function approve(int $linkId, ParentStudentLinkService $links): RedirectResponse
    {
        $links->approveRequest(Auth::user(), $linkId);

        return back()->with('success', 'تمت الموافقة على ربط حساب ولي الأمر.');
    }

    public function reject(int $linkId, ParentStudentLinkService $links): RedirectResponse
    {
        $links->rejectRequest(Auth::user(), $linkId);

        return back()->with('success', 'تم رفض طلب ربط حساب ولي الأمر.');
    }
}
