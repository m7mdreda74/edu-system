<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Application\Certificate\Services\CertificateService;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    public function __construct(
        private readonly CertificateService $certificates,
    ) {}

    public function show(int $groupId): Response
    {
        $student = Auth::user();
        $group   = TeachingGroup::findOrFail($groupId);

        // You must have studied with the group to hold its certificate.
        abort_unless(
            $student->subscriptions()->where('teaching_group_id', $group->id)->exists(),
            403,
            'لم تشترك في هذه المجموعة.',
        );

        abort_unless(
            $this->certificates->isEligible($student, $group),
            403,
            'لم تُكمل محتوى هذه المجموعة بعد.',
        );

        return Inertia::render('Student/Certificate', [
            'certificate' => $this->certificates->getCertificateData($student, $group),
            'group'       => [
                'id'   => $group->id,
                'name' => $group->name,
            ],
        ]);
    }
}
