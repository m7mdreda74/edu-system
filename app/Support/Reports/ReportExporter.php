<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Domain\Learning\Models\LiveSession;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExporter
{
    /**
     * Download the report as a formatted Excel (SpreadsheetML XML) file.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $filters
     */
    public function downloadExcel(string $reportType, array $data, array $filters): StreamedResponse
    {
        $today = now()->format('Y-m-d');
        $filename = "altafawwuq-report-{$reportType}-{$today}.xls";

        return new StreamedResponse(function () use ($reportType, $data, $filters): void {
            echo $this->buildSpreadsheetXml($reportType, $data, $filters);
        }, 200, [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma'              => 'public',
        ]);
    }

    /**
     * Generate the complete SpreadsheetML XML document string.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $filters
     */
    public function buildSpreadsheetXml(string $reportType, array $data, array $filters): string
    {
        $teachers   = collect($data['teachers'] ?? []);
        $students   = collect($data['students'] ?? []);
        $groups     = collect($data['groups'] ?? []);
        $sessions   = collect($data['sessions']['data'] ?? $data['sessions'] ?? []);
        $attendance = collect($data['attendance']['data'] ?? $data['attendance'] ?? []);
        $summary    = $data['summary'] ?? [];
        $filterDesc = $data['filterSummary'] ?? 'كافة البيانات دون فلاتر';

        $worksheets = [];

        if ($reportType === 'all' || $reportType === 'teachers') {
            $worksheets[] = $this->buildTeachersSheet($teachers, $summary, $filterDesc);
        }

        if ($reportType === 'all' || $reportType === 'students') {
            $worksheets[] = $this->buildStudentsSheet($students, $summary, $filterDesc);
        }

        if ($reportType === 'all' || $reportType === 'groups') {
            $worksheets[] = $this->buildGroupsSheet($groups, $summary, $filterDesc);
        }

        if ($reportType === 'all' || $reportType === 'sessions') {
            $worksheets[] = $this->buildSessionsSheet($sessions, $summary, $filterDesc);
        }

        if ($reportType === 'all' || $reportType === 'attendance') {
            $worksheets[] = $this->buildAttendanceSheet($attendance, $summary, $filterDesc);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        $xml .= $this->buildStyles();
        $xml .= implode("\n", $worksheets) . "\n";
        $xml .= '</Workbook>';

        return $xml;
    }

    private function buildStyles(): string
    {
        return <<<'XML'
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Center" ss:ReadingOrder="RightToLeft"/>
   <Borders/>
   <Font ss:FontName="Segoe UI" x:CharSet="1" ss:Size="10" ss:Color="#1E293B"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="TitleHeader">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:ReadingOrder="RightToLeft"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#0F172A"/>
   </Borders>
   <Font ss:FontName="Segoe UI" x:CharSet="1" ss:Size="14" ss:Color="#0F172A" ss:Bold="1"/>
   <Interior ss:Color="#E2E8F0" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="MetaSub">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center" ss:ReadingOrder="RightToLeft"/>
   <Font ss:FontName="Segoe UI" x:CharSet="1" ss:Size="9" ss:Color="#475569" ss:Italic="1"/>
   <Interior ss:Color="#F8FAFC" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="TableHeader">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:ReadingOrder="RightToLeft" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0F172A"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#0F172A"/>
   </Borders>
   <Font ss:FontName="Segoe UI" x:CharSet="1" ss:Size="10" ss:Color="#FFFFFF" ss:Bold="1"/>
   <Interior ss:Color="#1E293B" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="DataCell">
   <Alignment ss:Horizontal="Right" ss:Vertical="Center" ss:ReadingOrder="RightToLeft"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
   <Font ss:FontName="Segoe UI" x:CharSet="1" ss:Size="10" ss:Color="#334155"/>
  </Style>
  <Style ss:ID="DataCellCenter">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:ReadingOrder="RightToLeft"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/>
   </Borders>
   <Font ss:FontName="Segoe UI" x:CharSet="1" ss:Size="10" ss:Color="#334155"/>
  </Style>
  <Style ss:ID="BadgeGreen">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:ReadingOrder="RightToLeft"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBF7D0"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBF7D0"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBF7D0"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BBF7D0"/>
   </Borders>
   <Font ss:FontName="Segoe UI" x:CharSet="1" ss:Size="10" ss:Color="#166534" ss:Bold="1"/>
   <Interior ss:Color="#DCFCE7" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="BadgeRed">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:ReadingOrder="RightToLeft"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#FECACA"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#FECACA"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#FECACA"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#FECACA"/>
   </Borders>
   <Font ss:FontName="Segoe UI" x:CharSet="1" ss:Size="10" ss:Color="#991B1B" ss:Bold="1"/>
   <Interior ss:Color="#FEE2E2" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="BadgeBlue">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:ReadingOrder="RightToLeft"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BFDBFE"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BFDBFE"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BFDBFE"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BFDBFE"/>
   </Borders>
   <Font ss:FontName="Segoe UI" x:CharSet="1" ss:Size="10" ss:Color="#1E40AF" ss:Bold="1"/>
   <Interior ss:Color="#DBEAFE" ss:Pattern="Solid"/>
  </Style>
 </Styles>
XML;
    }

    /**
     * @param Collection<int, User> $teachers
     */
    private function buildTeachersSheet(Collection $teachers, array $summary, string $filterDesc): string
    {
        $headers = [
            'م', 'اسم المعلم', 'البريد الإلكتروني', 'رقم الهاتف', 'المادة التخصصية',
            'المراحل الدراسية المغطاة', 'فيديو تعريفي', 'التكليفات الأكاديمية',
            'المجموعات الدراسية', 'إجمالي الطلاب', 'الحصص المنعقدة', 'نسبة العمولة',
            'حالة الحساب', 'تاريخ الانضمام',
        ];

        $colWidths = [35, 140, 160, 110, 110, 150, 90, 100, 100, 90, 90, 85, 85, 100];

        $rows = [];
        // Title Row
        $rows[] = $this->makeTitleRow('منصة التفوق التعليمية — تقرير ملخص المعلمين والتكليفات', count($headers));
        $rows[] = $this->makeMetaRow("الفلاتر المطبقة: {$filterDesc} | تاريخ الاستخراج: " . now()->format('Y-m-d H:i') . " | إجمالي المعلمين: {$teachers->count()}", count($headers));
        $rows[] = $this->makeHeaderRow($headers);

        foreach ($teachers as $index => $teacher) {
            $grades = $teacher->teachingAssignments
                ->map(fn ($ta) => $ta->gradeLevel?->name)
                ->filter()
                ->unique()
                ->join('، ') ?: '—';

            $introStatus = $teacher->intro_video_url ? 'متوفر' : 'غير متوفر';
            $statusText = $teacher->is_active ? 'نشط' : 'معطل';
            $statusStyle = $teacher->is_active ? 'BadgeGreen' : 'BadgeRed';
            $createdAt = $teacher->created_at ? $teacher->created_at->format('Y-m-d') : '—';
            $commission = $teacher->commission_percent ? "{$teacher->commission_percent}%" : 'الافتراضية';

            $rows[] = '<Row ss:AutoFitHeight="0" ss:Height="22">'
                . $this->cell((string) ($index + 1), 'DataCellCenter', 'Number')
                . $this->cell((string) $teacher->name, 'DataCell')
                . $this->cell((string) $teacher->email, 'DataCellCenter')
                . $this->cell((string) ($teacher->phone ?: '—'), 'DataCellCenter')
                . $this->cell((string) ($teacher->subject?->name ?? 'غير محدد'), 'DataCell')
                . $this->cell($grades, 'DataCell')
                . $this->cell($introStatus, $teacher->intro_video_url ? 'BadgeGreen' : 'DataCellCenter')
                . $this->cell((string) ($teacher->teaching_assignments_count ?? $teacher->teachingAssignments->count()), 'DataCellCenter', 'Number')
                . $this->cell((string) ($teacher->groups_count ?? 0), 'DataCellCenter', 'Number')
                . $this->cell((string) ($teacher->students_count ?? 0), 'DataCellCenter', 'Number')
                . $this->cell((string) ($teacher->sessions_count ?? 0), 'DataCellCenter', 'Number')
                . $this->cell($commission, 'DataCellCenter')
                . $this->cell($statusText, $statusStyle)
                . $this->cell($createdAt, 'DataCellCenter')
                . '</Row>';
        }

        return $this->wrapWorksheet('المعلمون', $colWidths, $rows);
    }

    /**
     * @param Collection<int, User> $students
     */
    private function buildStudentsSheet(Collection $students, array $summary, string $filterDesc): string
    {
        $headers = [
            'م', 'اسم الطالب', 'البريد الإلكتروني', 'رقم الهاتف', 'المرحلة والصف الدراسي',
            'اسم ولي الأمر', 'هاتف ولي الأمر', 'الاشتراكات الفعالة', 'تفاصيل الاشتراكات والمواد',
            'الحصص المباشرة المحضورة', 'مدة الحضور (دقائق)', 'مدة الحضور (ساعات)', 'حالة الحساب', 'تاريخ التسجيل',
        ];

        $colWidths = [35, 140, 160, 110, 130, 120, 110, 100, 200, 110, 100, 100, 85, 100];

        $rows = [];
        $rows[] = $this->makeTitleRow('منصة التفوق التعليمية — تقرير بيانات ومتابعة الطلاب الشامل', count($headers));
        $rows[] = $this->makeMetaRow("الفلاتر المطبقة: {$filterDesc} | تاريخ الاستخراج: " . now()->format('Y-m-d H:i') . " | إجمالي الطلاب: {$students->count()}", count($headers));
        $rows[] = $this->makeHeaderRow($headers);

        foreach ($students as $index => $student) {
            $statusText = $student->is_active ? 'نشط' : 'معطل';
            $statusStyle = $student->is_active ? 'BadgeGreen' : 'BadgeRed';
            $createdAt = $student->created_at ? $student->created_at->format('Y-m-d') : '—';

            $rows[] = '<Row ss:AutoFitHeight="0" ss:Height="22">'
                . $this->cell((string) ($index + 1), 'DataCellCenter', 'Number')
                . $this->cell((string) $student->name, 'DataCell')
                . $this->cell((string) $student->email, 'DataCellCenter')
                . $this->cell((string) ($student->phone ?: '—'), 'DataCellCenter')
                . $this->cell((string) ($student->grade_name ?? 'غير محدد'), 'DataCell')
                . $this->cell((string) ($student->parent_name ?? '—'), 'DataCell')
                . $this->cell((string) ($student->parent_phone ?? '—'), 'DataCellCenter')
                . $this->cell((string) ($student->active_subscriptions_count ?? 0), 'DataCellCenter', 'Number')
                . $this->cell((string) ($student->active_subscriptions_details ?? 'لا توجد'), 'DataCell')
                . $this->cell((string) ($student->sessions_attended_count ?? 0), 'DataCellCenter', 'Number')
                . $this->cell((string) ($student->attendance_minutes ?? 0), 'DataCellCenter', 'Number')
                . $this->cell((string) ($student->attendance_hours ?? 0), 'DataCellCenter', 'Number')
                . $this->cell($statusText, $statusStyle)
                . $this->cell($createdAt, 'DataCellCenter')
                . '</Row>';
        }

        return $this->wrapWorksheet('الطلاب', $colWidths, $rows);
    }

    /**
     * @param Collection<int, TeachingGroup> $groups
     */
    private function buildGroupsSheet(Collection $groups, array $summary, string $filterDesc): string
    {
        $headers = [
            'م', 'اسم المجموعة', 'المعلم المسؤول', 'المادة الدراسية', 'المرحلة الدراسية',
            'الفصل الأكاديمي', 'الطلاب المحجوزون', 'السعة القصوى', 'نسبة الإشغال',
            'الحصص المنعقدة', 'حالة المجموعة',
        ];

        $colWidths = [35, 160, 130, 110, 130, 100, 100, 90, 85, 90, 85];

        $rows = [];
        $rows[] = $this->makeTitleRow('منصة التفوق التعليمية — تقرير المجموعات الدراسية والدورات', count($headers));
        $rows[] = $this->makeMetaRow("الفلاتر المطبقة: {$filterDesc} | تاريخ الاستخراج: " . now()->format('Y-m-d H:i') . " | إجمالي المجموعات: {$groups->count()}", count($headers));
        $rows[] = $this->makeHeaderRow($headers);

        foreach ($groups as $index => $group) {
            $occupancy = round((($group->active_bookings_count ?? 0) / max(1, (int) ($group->capacity ?? 1))) * 100);
            $statusText = $group->is_active ? 'نشطة' : 'متوقفة';
            $statusStyle = $group->is_active ? 'BadgeGreen' : 'BadgeRed';

            $rows[] = '<Row ss:AutoFitHeight="0" ss:Height="22">'
                . $this->cell((string) ($index + 1), 'DataCellCenter', 'Number')
                . $this->cell((string) $group->name, 'DataCell')
                . $this->cell((string) ($group->assignment?->teacher?->name ?? '—'), 'DataCell')
                . $this->cell((string) ($group->assignment?->subject?->name ?? '—'), 'DataCell')
                . $this->cell((string) ($group->assignment?->gradeLevel?->name ?? '—'), 'DataCell')
                . $this->cell((string) ($group->term?->name ?? '—'), 'DataCellCenter')
                . $this->cell((string) ($group->active_bookings_count ?? 0), 'DataCellCenter', 'Number')
                . $this->cell((string) ($group->capacity ?? 0), 'DataCellCenter', 'Number')
                . $this->cell("{$occupancy}%", 'DataCellCenter')
                . $this->cell((string) ($group->live_sessions_count ?? 0), 'DataCellCenter', 'Number')
                . $this->cell($statusText, $statusStyle)
                . '</Row>';
        }

        return $this->wrapWorksheet('المجموعات والدورات', $colWidths, $rows);
    }

    /**
     * @param Collection<int, LiveSession> $sessions
     */
    private function buildSessionsSheet(Collection $sessions, array $summary, string $filterDesc): string
    {
        $headers = [
            'م', 'عنوان الحصة', 'المعلم', 'نوع الحصة', 'المادة / المجموعة',
            'الموعد والتاريخ', 'عدد الحاضرين', 'حالة الحصة',
        ];

        $colWidths = [35, 180, 130, 120, 160, 140, 90, 95];

        $rows = [];
        $rows[] = $this->makeTitleRow('منصة التفوق التعليمية — تقرير الحصص المباشرة', count($headers));
        $rows[] = $this->makeMetaRow("الفلاتر المطبقة: {$filterDesc} | تاريخ الاستخراج: " . now()->format('Y-m-d H:i') . " | إجمالي الحصص: {$sessions->count()}", count($headers));
        $rows[] = $this->makeHeaderRow($headers);

        $statusLabels = [
            'scheduled' => 'مجدولة',
            'live'      => 'مباشرة الآن',
            'ended'     => 'منتهية',
            'cancelled' => 'ملغاة',
        ];

        foreach ($sessions as $index => $session) {
            $typeLabel = $session->private_session_slot_id || ($session->privateSessionSlot ?? false)
                ? 'مرافقة خاصة'
                : ($session->is_free ? 'مجانية / تجريبية' : 'مجموعة دراسية');

            $subjectOrGroup = $session->teachingGroup?->assignment?->subject?->name
                ?? $session->privateSessionSlot?->assignment?->subject?->name
                ?? ($session->teachingGroup?->name ?? 'حصة خاصة');

            $dateFormatted = $session->scheduled_at
                ? CarbonImmutable::parse($session->scheduled_at)->format('Y-m-d H:i')
                : '—';

            $statusText = $statusLabels[$session->status] ?? $session->status;
            $statusStyle = match ($session->status) {
                'live'      => 'BadgeGreen',
                'ended'     => 'BadgeBlue',
                'cancelled' => 'BadgeRed',
                default     => 'DataCellCenter',
            };

            $rows[] = '<Row ss:AutoFitHeight="0" ss:Height="22">'
                . $this->cell((string) ($index + 1), 'DataCellCenter', 'Number')
                . $this->cell((string) $session->title, 'DataCell')
                . $this->cell((string) ($session->teacher?->name ?? '—'), 'DataCell')
                . $this->cell($typeLabel, 'DataCellCenter')
                . $this->cell($subjectOrGroup, 'DataCell')
                . $this->cell($dateFormatted, 'DataCellCenter')
                . $this->cell((string) ($session->attendees_count ?? 0), 'DataCellCenter', 'Number')
                . $this->cell($statusText, $statusStyle)
                . '</Row>';
        }

        return $this->wrapWorksheet('الحصص المباشرة', $colWidths, $rows);
    }

    /**
     * @param Collection<int, array<string, mixed>> $attendance
     */
    private function buildAttendanceSheet(Collection $attendance, array $summary, string $filterDesc): string
    {
        $headers = [
            'م', 'اسم الطالب', 'البريد الإلكتروني', 'المعلم', 'الحصة / المادة',
            'الموعد المحدد', 'وقت الدخول', 'وقت الخروج', 'مدة الحضور (دقائق)',
        ];

        $colWidths = [35, 140, 160, 130, 180, 130, 130, 130, 110];

        $rows = [];
        $rows[] = $this->makeTitleRow('منصة التفوق التعليمية — تقرير سجل حضور الطلاب الفعلي', count($headers));
        $rows[] = $this->makeMetaRow("الفلاتر المطبقة: {$filterDesc} | تاريخ الاستخراج: " . now()->format('Y-m-d H:i') . " | إجمالي السجلات: {$attendance->count()}", count($headers));
        $rows[] = $this->makeHeaderRow($headers);

        foreach ($attendance as $index => $row) {
            $student = is_array($row['student'] ?? null) ? $row['student'] : [];
            $teacher = is_array($row['teacher'] ?? null) ? $row['teacher'] : [];

            $scheduledAt = ! empty($row['scheduled_at']) ? CarbonImmutable::parse($row['scheduled_at'])->format('Y-m-d H:i') : '—';
            $joinedAt    = ! empty($row['joined_at']) ? CarbonImmutable::parse($row['joined_at'])->format('Y-m-d H:i:s') : '—';
            $leftAt      = ! empty($row['left_at']) ? CarbonImmutable::parse($row['left_at'])->format('Y-m-d H:i:s') : '—';

            $rows[] = '<Row ss:AutoFitHeight="0" ss:Height="22">'
                . $this->cell((string) ($index + 1), 'DataCellCenter', 'Number')
                . $this->cell((string) ($student['name'] ?? '—'), 'DataCell')
                . $this->cell((string) ($student['email'] ?? '—'), 'DataCellCenter')
                . $this->cell((string) ($teacher['name'] ?? '—'), 'DataCell')
                . $this->cell((string) ($row['session'] ?? '—'), 'DataCell')
                . $this->cell($scheduledAt, 'DataCellCenter')
                . $this->cell($joinedAt, 'DataCellCenter')
                . $this->cell($leftAt, 'DataCellCenter')
                . $this->cell((string) ($row['minutes'] ?? 0), 'DataCellCenter', 'Number')
                . '</Row>';
        }

        return $this->wrapWorksheet('سجل الحضور والغياب', $colWidths, $rows);
    }

    private function makeTitleRow(string $title, int $colSpan): string
    {
        $escaped = htmlspecialchars($title, ENT_XML1, 'UTF-8');
        return "<Row ss:AutoFitHeight=\"0\" ss:Height=\"30\"><Cell ss:StyleID=\"TitleHeader\" ss:MergeAcross=\"" . ($colSpan - 1) . "\"><Data ss:Type=\"String\">{$escaped}</Data></Cell></Row>";
    }

    private function makeMetaRow(string $meta, int $colSpan): string
    {
        $escaped = htmlspecialchars($meta, ENT_XML1, 'UTF-8');
        return "<Row ss:AutoFitHeight=\"0\" ss:Height=\"20\"><Cell ss:StyleID=\"MetaSub\" ss:MergeAcross=\"" . ($colSpan - 1) . "\"><Data ss:Type=\"String\">{$escaped}</Data></Cell></Row>";
    }

    /**
     * @param array<int, string> $headers
     */
    private function makeHeaderRow(array $headers): string
    {
        $cells = array_map(fn ($h) => $this->cell($h, 'TableHeader', 'String'), $headers);
        return '<Row ss:AutoFitHeight="0" ss:Height="26">' . implode('', $cells) . '</Row>';
    }

    private function cell(string $val, string $styleId = 'DataCell', string $type = 'String'): string
    {
        $escaped = htmlspecialchars($val, ENT_XML1, 'UTF-8');
        return "<Cell ss:StyleID=\"{$styleId}\"><Data ss:Type=\"{$type}\">{$escaped}</Data></Cell>";
    }

    /**
     * @param array<int, int> $colWidths
     * @param array<int, string> $rows
     */
    private function wrapWorksheet(string $name, array $colWidths, array $rows): string
    {
        $cleanName = htmlspecialchars(mb_substr($name, 0, 31), ENT_XML1, 'UTF-8');
        $xml = " <Worksheet ss:Name=\"{$cleanName}\" ss:RightToLeft=\"1\">\n";
        $xml .= "  <Table ss:DefaultRowHeight=\"20\">\n";
        foreach ($colWidths as $w) {
            $xml .= "   <Column ss:AutoFitWidth=\"1\" ss:Width=\"{$w}\"/>\n";
        }
        $xml .= "   " . implode("\n   ", $rows) . "\n";
        $xml .= "  </Table>\n";
        $xml .= " </Worksheet>";

        return $xml;
    }
}
