<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Reports\ReportDataService;
use App\Support\Reports\ReportExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportDataService $reportDataService,
        private readonly ReportExporter $reportExporter,
    ) {
    }

    public function index(Request $request): Response
    {
        $data = $this->reportDataService->getReportData($request, false);

        return Inertia::render('Admin/Reports', [
            'printMode'     => $data['printMode'],
            'filters'       => $data['filters'],
            'filterSummary' => $data['filterSummary'],
            'teachers'      => $data['teachers'],
            'students'      => $data['students'],
            'groups'        => $data['groups'],
            'sessions'      => $data['sessions'],
            'attendance'    => $data['attendance'],
            'summary'       => $data['summary'],
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $data = $this->reportDataService->getReportData($request, true);

        return $this->reportExporter->downloadExcel(
            $data['reportType'],
            $data,
            $data['filters'],
        );
    }

    public function exportPdf(Request $request): View
    {
        $data = $this->reportDataService->getReportData($request, true);

        $categoryTitles = [
            'teachers'   => 'تقرير ملخص المعلمين والتكليفات الأكاديمية',
            'students'   => 'تقرير بيانات ومتابعة الطلاب الشامل',
            'attendance' => 'سجل حضور الطلاب الفعلي',
            'sessions'   => 'تقرير الحصص المباشرة',
            'groups'     => 'تقرير المجموعات والدورات الدراسية',
            'all'        => 'التقرير الإداري الشامل لجميع المدرسين والطلاب',
        ];

        $categoryNames = [
            'teachers'   => 'تقرير المعلمين',
            'students'   => 'تقرير الطلاب',
            'attendance' => 'حضور الطلاب',
            'sessions'   => 'الحصص المباشرة',
            'groups'     => 'المجموعات والدورات',
            'all'        => 'التقرير الشامل للمنصة',
        ];

        $reportType = $data['reportType'];

        return view('reports.pdf', array_merge($data, [
            'activeCategoryTitle' => $categoryTitles[$reportType] ?? 'تقرير منصة التفوق التعليمية',
            'reportTypeName'      => $categoryNames[$reportType] ?? 'تقرير إداري',
        ]));
    }
}
