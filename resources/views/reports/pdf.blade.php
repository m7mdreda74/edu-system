<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $activeCategoryTitle ?? 'تقرير منصة التفوق التعليمية' }} — {{ now()->format('Y-m-d') }}</title>
    
    <!-- Google Fonts for crisp Arabic typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">

    <style>
        /* ── Reset & Base Styles ────────────────────────────────────────── */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Cairo', 'Tajawal', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            line-height: 1.45;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Floating Interactive Screen Control Bar ───────────────────── */
        .screen-toolbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #0f172a;
            color: #ffffff;
            padding: 12px 24px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .screen-toolbar .brand-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .screen-toolbar .brand-badge {
            background: #2563eb;
            color: #ffffff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .screen-toolbar .toolbar-title {
            font-size: 15px;
            font-weight: 700;
            color: #f8fafc;
        }

        .screen-toolbar .actions-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .btn-print {
            background-color: #059669;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        }

        .btn-print:hover {
            background-color: #047857;
        }

        .btn-excel {
            background-color: #1d4ed8;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(29, 78, 216, 0.3);
        }

        .btn-excel:hover {
            background-color: #1e40af;
        }

        .btn-back {
            background-color: #334155;
            color: #f1f5f9;
        }

        .btn-back:hover {
            background-color: #475569;
        }

        .tip-banner {
            background-color: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            margin: 12px auto;
            max-width: 1280px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── Document Container ────────────────────────────────────────── */
        .report-page-container {
            max-width: 1280px;
            margin: 20px auto 40px;
            background: #ffffff;
            padding: 36px 40px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        /* ── Document Header ──────────────────────────────────────────── */
        .report-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 18px;
            margin-bottom: 18px;
            gap: 20px;
        }

        .report-header .title-area h1 {
            font-size: 22px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .report-header .title-area .subtitle {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }

        .meta-stamp-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 16px;
            min-width: 280px;
            font-size: 11px;
            line-height: 1.7;
        }

        .meta-stamp-card .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .meta-stamp-card .meta-label {
            color: #64748b;
            font-weight: 600;
        }

        .meta-stamp-card .meta-val {
            color: #0f172a;
            font-weight: 700;
        }

        /* ── Filter Banner ────────────────────────────────────────────── */
        .filter-summary-strip {
            background: #f1f5f9;
            border-right: 4px solid #2563eb;
            padding: 8px 14px;
            border-radius: 0 6px 6px 0;
            font-size: 11px;
            color: #334155;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-summary-strip strong {
            color: #0f172a;
        }

        /* ── KPI Summary Cards ────────────────────────────────────────── */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-bottom: 24px;
        }

        .kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
        }

        .kpi-card .val {
            font-size: 18px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .kpi-card .lbl {
            font-size: 10.5px;
            font-weight: 700;
            color: #64748b;
        }

        /* ── Report Section & Tables ─────────────────────────────────── */
        .report-section {
            margin-bottom: 28px;
            page-break-inside: auto;
            break-inside: auto;
        }

        .section-headline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
            border-right: 4px solid #0f172a;
            padding: 8px 14px;
            margin-bottom: 12px;
            border-radius: 0 6px 6px 0;
            page-break-after: avoid;
            break-after: avoid;
        }

        .section-headline h2 {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
        }

        .section-headline .count-badge {
            font-size: 11px;
            font-weight: 700;
            background: #e2e8f0;
            color: #334155;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            text-align: right;
            border: 1px solid #cbd5e1;
        }

        .report-table thead {
            display: table-header-group;
        }

        .report-table th {
            background-color: #1e293b;
            color: #ffffff;
            padding: 7px 9px;
            font-weight: 700;
            border: 1px solid #0f172a;
            white-space: nowrap;
        }

        .report-table td {
            padding: 6px 9px;
            border: 1px solid #e2e8f0;
            color: #334155;
            vertical-align: middle;
        }

        .report-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .report-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .text-center { text-align: center; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .font-bold { font-weight: 700; }

        .badge-active {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .badge-inactive {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .badge-blue {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .badge-purple {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            background: #f3e8ff;
            color: #6b21a8;
            border: 1px solid #e9d5ff;
        }

        .empty-cell {
            text-align: center;
            padding: 18px;
            color: #94a3b8;
            font-size: 12px;
        }

        /* ── Document Footer & Stamps ─────────────────────────────────── */
        .report-footer {
            margin-top: 36px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 18px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            font-size: 11px;
            color: #64748b;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .stamp-box {
            border: 1px dashed #94a3b8;
            border-radius: 8px;
            padding: 10px 18px;
            text-align: center;
            min-width: 180px;
        }

        .stamp-box .stamp-title {
            font-weight: 700;
            color: #334155;
            margin-bottom: 25px;
        }

        /* ── Exact Print Media Overrides ──────────────────────────────── */
        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm 12mm 12mm 12mm;
            }

            body {
                background: #ffffff !important;
                color: #0f172a !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .screen-toolbar, .tip-banner {
                display: none !important;
            }

            .report-page-container {
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                background: #ffffff !important;
            }

            .report-table {
                border-color: #94a3b8 !important;
            }

            .report-table th {
                background-color: #0f172a !important;
                color: #ffffff !important;
                border-color: #0f172a !important;
            }

            .report-table td {
                border-color: #cbd5e1 !important;
            }

            .badge-active, .badge-inactive, .badge-blue, .badge-purple {
                border: 1px solid #94a3b8 !important;
                background: transparent !important;
                color: #0f172a !important;
            }
        }
    </style>
</head>
<body>

    <!-- ── Floating Screen Toolbar (Hidden when printing/PDF) ─────── -->
    <div class="screen-toolbar">
        <div class="brand-group">
            <span class="brand-badge">معتمد A4 بالعرض</span>
            <span class="toolbar-title">📄 مستند التقرير الأكاديمي المنسق — منصة التفوق</span>
        </div>
        <div class="actions-group">
            <button type="button" class="btn btn-print" onclick="window.print()">
                <span>🖨️ طباعة أو حفظ كـ PDF</span>
            </button>
            <a href="{{ route('admin.reports.export.excel', request()->all()) }}" class="btn btn-excel">
                <span>📊 تصدير إلى Excel</span>
            </a>
            <a href="{{ route('admin.reports', request()->all()) }}" class="btn btn-back">
                <span>↩️ العودة للتقارير</span>
            </a>
        </div>
    </div>

    <!-- Screen Tip Banner -->
    <div class="tip-banner">
        <span>💡</span>
        <span><strong>نصيحة لحفظ PDF مثالي:</strong> اضغط على الزر الأخضر "طباعة أو حفظ كـ PDF"، واختر الوجهة (Destination: <strong>Save as PDF</strong>)، وتأكد من ضبط الاتجاه على <strong>أفقي (Landscape)</strong> وإلغاء خيار الرؤوس والتذييلات الافتراضية للمتصفح.</span>
    </div>

    <!-- ── Report Document ────────────────────────────────────────── -->
    <div class="report-page-container">

        <!-- Header -->
        <header class="report-header">
            <div class="title-area">
                <h1>منصة التفوق التعليمية</h1>
                <div class="subtitle">نظام الإدارة الأكاديمية الذكي — {{ $activeCategoryTitle ?? 'التقرير الإداري الشامل لجميع المدرسين والطلاب' }}</div>
            </div>
            <div class="meta-stamp-card">
                <div class="meta-row">
                    <span class="meta-label">تاريخ ووقت الإصدار:</span>
                    <span class="meta-val font-mono">{{ now()->format('Y-m-d H:i') }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">المسؤول المصدر:</span>
                    <span class="meta-val">{{ auth()->user()?->name ?? 'الإدارة العامة' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">نوع التقرير:</span>
                    <span class="meta-val font-bold">{{ $reportTypeName ?? 'تقرير شامل' }}</span>
                </div>
            </div>
        </header>

        <!-- Filters Description Strip -->
        <div class="filter-summary-strip">
            <span>🔍 <strong>الفلاتر المطبقة:</strong></span>
            <span>{{ $filterSummary ?? 'كافة بيانات المنصة دون فلاتر' }}</span>
        </div>

        <!-- KPI Summary Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="val">{{ $summary['teachers'] ?? count($teachers) }}</div>
                <div class="lbl">👨‍🏫 المدرسون</div>
            </div>
            <div class="kpi-card">
                <div class="val">{{ $summary['students_registered'] ?? count($students) }}</div>
                <div class="lbl">🎓 إجمالي الطلاب</div>
            </div>
            <div class="kpi-card">
                <div class="val">{{ $summary['active_subscriptions'] ?? 0 }}</div>
                <div class="lbl">💳 اشتراكات فعالة</div>
            </div>
            <div class="kpi-card">
                <div class="val">{{ $summary['groups'] ?? count($groups) }}</div>
                <div class="lbl">📚 المجموعات</div>
            </div>
            <div class="kpi-card">
                <div class="val">{{ $summary['sessions'] ?? (is_countable($sessions['data'] ?? null) ? count($sessions['data']) : count($sessions)) }}</div>
                <div class="lbl">🎥 الحصص المباشرة</div>
            </div>
            <div class="kpi-card">
                <div class="val">{{ $summary['total_attendance_hours'] ?? 0 }} س</div>
                <div class="lbl">⏱️ ساعات الحضور</div>
            </div>
        </div>

        <!-- ──────────────────────────────────────────────────────── -->
        <!-- 1. Teachers Section                                      -->
        <!-- ──────────────────────────────────────────────────────── -->
        @if ($reportType === 'all' || $reportType === 'teachers')
        <section class="report-section">
            <div class="section-headline">
                <h2>👨‍🏫 تقرير ملخص المدرسين والتكليفات الأكاديمية</h2>
                <span class="count-badge">{{ count($teachers) }} معلم مسجل</span>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>المدرس</th>
                        <th>بيانات الاتصال</th>
                        <th>المادة والتخصص</th>
                        <th>المراحل المغطاة</th>
                        <th>الفيديو التعريفي</th>
                        <th class="text-center">المجموعات</th>
                        <th class="text-center">إجمالي الطلاب</th>
                        <th class="text-center">الحصص</th>
                        <th class="text-center">العمولة</th>
                        <th class="text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($teachers as $idx => $teacher)
                    @php
                        $grades = $teacher->teachingAssignments
                            ->map(fn($ta) => $ta->gradeLevel?->name)
                            ->filter()
                            ->unique()
                            ->join('، ') ?: '—';
                    @endphp
                    <tr>
                        <td class="text-center font-mono">{{ $idx + 1 }}</td>
                        <td class="font-bold">{{ $teacher->name }}</td>
                        <td>
                            <div>{{ $teacher->email }}</div>
                            <div class="font-mono text-xs" style="color: #64748b;">{{ $teacher->phone ?: '—' }}</div>
                        </td>
                        <td>{{ $teacher->subject?->name ?? 'غير محدد' }}</td>
                        <td style="max-width: 180px;">{{ $grades }}</td>
                        <td class="text-center">
                            @if ($teacher->intro_video_url)
                                <span class="badge-active">🎬 متوفر</span>
                            @else
                                <span style="color: #94a3b8;">بدون فيديو</span>
                            @endif
                        </td>
                        <td class="text-center font-bold">{{ $teacher->groups_count ?? 0 }}</td>
                        <td class="text-center font-bold" style="color: #1e40af;">{{ $teacher->students_count ?? 0 }} طالب</td>
                        <td class="text-center font-bold">{{ $teacher->sessions_count ?? 0 }}</td>
                        <td class="text-center font-mono">{{ $teacher->commission_percent ? $teacher->commission_percent . '%' : 'افتراضية' }}</td>
                        <td class="text-center">
                            <span class="{{ $teacher->is_active ? 'badge-active' : 'badge-inactive' }}">
                                {{ $teacher->is_active ? 'نشط' : 'معطل' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="empty-cell">لا توجد بيانات مطابقة لفلاتر المدرسين.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        @endif

        <!-- ──────────────────────────────────────────────────────── -->
        <!-- 2. Students Section                                      -->
        <!-- ──────────────────────────────────────────────────────── -->
        @if ($reportType === 'all' || $reportType === 'students')
        <section class="report-section">
            <div class="section-headline">
                <h2>🎓 تقرير بيانات ومتابعة الطلاب الشامل (الاشتراكات وساعات الحضور)</h2>
                <span class="count-badge">{{ count($students) }} طالب</span>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>اسم الطالب</th>
                        <th>بيانات الاتصال</th>
                        <th>المرحلة والصف الدراسي</th>
                        <th>ولي الأمر وهاتفه</th>
                        <th class="text-center">الاشتراكات الفعالة</th>
                        <th>تفاصيل المواد والمعلمين</th>
                        <th class="text-center">الحصص المحضورة</th>
                        <th class="text-center">مدة الحضور</th>
                        <th class="text-center">الحالة</th>
                        <th class="text-center">تاريخ الانضمام</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $idx => $student)
                    <tr>
                        <td class="text-center font-mono">{{ $idx + 1 }}</td>
                        <td class="font-bold">{{ $student->name }}</td>
                        <td>
                            <div>{{ $student->email }}</div>
                            <div class="font-mono text-xs" style="color: #64748b;">{{ $student->phone ?: '—' }}</div>
                        </td>
                        <td>{{ $student->grade_name ?? 'غير محدد' }}</td>
                        <td>
                            <div>{{ $student->parent_name ?: '—' }}</div>
                            <div class="font-mono text-xs" style="color: #64748b;">{{ $student->parent_phone ?: '—' }}</div>
                        </td>
                        <td class="text-center font-bold">
                            @if (($student->active_subscriptions_count ?? 0) > 0)
                                <span class="badge-active">{{ $student->active_subscriptions_count }} اشتراك</span>
                            @else
                                <span style="color: #94a3b8;">0</span>
                            @endif
                        </td>
                        <td style="max-width: 220px; font-size: 10px;">{{ $student->active_subscriptions_details ?? 'لا توجد' }}</td>
                        <td class="text-center font-bold">{{ $student->sessions_attended_count ?? 0 }}</td>
                        <td class="text-center font-mono font-bold" style="color: #059669;">
                            {{ $student->attendance_hours ?? 0 }} س ({{ $student->attendance_minutes ?? 0 }} د)
                        </td>
                        <td class="text-center">
                            <span class="{{ $student->is_active ? 'badge-active' : 'badge-inactive' }}">
                                {{ $student->is_active ? 'نشط' : 'معطل' }}
                            </span>
                        </td>
                        <td class="text-center font-mono text-xs">{{ $student->created_at ? $student->created_at->format('Y-m-d') : '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="empty-cell">لا توجد بيانات مطابقة لفلاتر الطلاب.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        @endif

        <!-- ──────────────────────────────────────────────────────── -->
        <!-- 3. Groups Section                                        -->
        <!-- ──────────────────────────────────────────────────────── -->
        @if ($reportType === 'all' || $reportType === 'groups')
        <section class="report-section">
            <div class="section-headline">
                <h2>📚 تقرير المجموعات الدراسية والدورات</h2>
                <span class="count-badge">{{ count($groups) }} مجموعة دراسية</span>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>المجموعة</th>
                        <th>المعلم المسؤول</th>
                        <th>المادة والمرحلة</th>
                        <th>الفصل الأكاديمي</th>
                        <th class="text-center">الحجوزات الفعالة</th>
                        <th class="text-center">السعة</th>
                        <th class="text-center">نسبة الإشغال</th>
                        <th class="text-center">الحصص المنعقدة</th>
                        <th class="text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($groups as $idx => $group)
                    @php
                        $occupancy = round((($group->active_bookings_count ?? 0) / max(1, (int) ($group->capacity ?? 1))) * 100);
                    @endphp
                    <tr>
                        <td class="text-center font-mono">{{ $idx + 1 }}</td>
                        <td class="font-bold">{{ $group->name }}</td>
                        <td>{{ $group->assignment?->teacher?->name ?? '—' }}</td>
                        <td>
                            <div>{{ $group->assignment?->subject?->name ?? '—' }}</div>
                            <div class="text-xs" style="color: #64748b;">{{ $group->assignment?->gradeLevel?->name ?? '' }}</div>
                        </td>
                        <td>{{ $group->term?->name ?? '—' }}</td>
                        <td class="text-center font-bold" style="color: #1e40af;">{{ $group->active_bookings_count ?? 0 }} طالب</td>
                        <td class="text-center font-mono">{{ $group->capacity }}</td>
                        <td class="text-center font-mono font-bold">{{ $occupancy }}%</td>
                        <td class="text-center font-bold">{{ $group->live_sessions_count ?? 0 }}</td>
                        <td class="text-center">
                            <span class="{{ $group->is_active ? 'badge-active' : 'badge-inactive' }}">
                                {{ $group->is_active ? 'نشطة' : 'متوقفة' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="empty-cell">لا توجد مجموعات مطابقة للفلاتر.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        @endif

        <!-- ──────────────────────────────────────────────────────── -->
        <!-- 4. Live Sessions Section                                 -->
        <!-- ──────────────────────────────────────────────────────── -->
        @if ($reportType === 'all' || $reportType === 'sessions')
        @php
            $sessionList = $sessions['data'] ?? $sessions;
        @endphp
        <section class="report-section">
            <div class="section-headline">
                <h2>🎥 تقرير الحصص المباشرة</h2>
                <span class="count-badge">{{ is_countable($sessionList) ? count($sessionList) : 0 }} حصة</span>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>عنوان الحصة</th>
                        <th>المعلم</th>
                        <th class="text-center">نوع الحصة</th>
                        <th>المادة والمجموعة</th>
                        <th class="text-center">الموعد المحدد</th>
                        <th class="text-center">الحضور</th>
                        <th class="text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessionList as $idx => $session)
                    @php
                        $isPrivate = $session->private_session_slot_id || ($session->privateSessionSlot ?? false);
                        $typeLabel = $isPrivate ? 'مرافقة خاصة' : ($session->is_free ? 'مجانية / تجريبية' : 'مجموعة دراسية');
                        $typeClass = $isPrivate ? 'badge-purple' : ($session->is_free ? 'badge-active' : 'badge-blue');

                        $statusMap = [
                            'scheduled' => ['مجدولة', 'badge-blue'],
                            'live'      => ['مباشرة الآن', 'badge-active'],
                            'ended'     => ['منتهية', 'badge-blue'],
                            'cancelled' => ['ملغاة', 'badge-inactive'],
                        ];
                        $st = $statusMap[$session->status] ?? [$session->status, ''];
                    @endphp
                    <tr>
                        <td class="text-center font-mono">{{ $idx + 1 }}</td>
                        <td class="font-bold">{{ $session->title }}</td>
                        <td>{{ $session->teacher?->name ?? '—' }}</td>
                        <td class="text-center">
                            <span class="{{ $typeClass }}">{{ $typeLabel }}</span>
                        </td>
                        <td>
                            <div>{{ $session->teachingGroup?->assignment?->subject?->name ?? $session->privateSessionSlot?->assignment?->subject?->name ?? 'حصة خاصة' }}</div>
                            <div class="text-xs" style="color: #64748b;">{{ $session->teachingGroup?->name ?? 'موعد فردي' }}</div>
                        </td>
                        <td class="text-center font-mono text-xs">
                            {{ $session->scheduled_at ? \Carbon\CarbonImmutable::parse($session->scheduled_at)->format('Y-m-d H:i') : '—' }}
                        </td>
                        <td class="text-center font-bold">{{ $session->attendees_count ?? 0 }}</td>
                        <td class="text-center">
                            <span class="{{ $st[1] }}">{{ $st[0] }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="empty-cell">لا توجد حصص مباشرة مطابقة للفلاتر.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        @endif

        <!-- ──────────────────────────────────────────────────────── -->
        <!-- 5. Attendance Section                                    -->
        <!-- ──────────────────────────────────────────────────────── -->
        @if ($reportType === 'all' || $reportType === 'attendance')
        @php
            $attList = $attendance['data'] ?? $attendance;
        @endphp
        <section class="report-section">
            <div class="section-headline">
                <h2>⏱️ تقرير سجل حضور وغياب الطلاب الفعلي</h2>
                <span class="count-badge">{{ is_countable($attList) ? count($attList) : 0 }} سجل حضور</span>
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>الطالب</th>
                        <th>المدرس</th>
                        <th>الحصة / المادة</th>
                        <th class="text-center">موعد الحصة</th>
                        <th class="text-center">وقت الدخول</th>
                        <th class="text-center">وقت الخروج</th>
                        <th class="text-center">مدة الحضور</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($attList as $idx => $row)
                    @php
                        $studentData = is_array($row['student'] ?? null) ? $row['student'] : [];
                        $teacherData = is_array($row['teacher'] ?? null) ? $row['teacher'] : [];
                    @endphp
                    <tr>
                        <td class="text-center font-mono">{{ $idx + 1 }}</td>
                        <td>
                            <div class="font-bold">{{ $studentData['name'] ?? '—' }}</div>
                            <div class="font-mono text-xs" style="color: #64748b;">{{ $studentData['email'] ?? '' }}</div>
                        </td>
                        <td>{{ $teacherData['name'] ?? '—' }}</td>
                        <td>
                            <div>{{ $row['session'] ?? '—' }}</div>
                            <div class="text-xs" style="color: #64748b;">{{ $row['subject'] ?? '—' }}</div>
                        </td>
                        <td class="text-center font-mono text-xs">
                            {{ !empty($row['scheduled_at']) ? \Carbon\CarbonImmutable::parse($row['scheduled_at'])->format('Y-m-d H:i') : '—' }}
                        </td>
                        <td class="text-center font-mono text-xs" style="color: #059669;">
                            {{ !empty($row['joined_at']) ? \Carbon\CarbonImmutable::parse($row['joined_at'])->format('H:i:s') : '—' }}
                        </td>
                        <td class="text-center font-mono text-xs" style="color: #64748b;">
                            {{ !empty($row['left_at']) ? \Carbon\CarbonImmutable::parse($row['left_at'])->format('H:i:s') : '—' }}
                        </td>
                        <td class="text-center font-mono font-bold" style="color: #0f172a;">
                            {{ $row['minutes'] ?? 0 }} دقيقة
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="empty-cell">لا توجد سجلات حضور مطابقة للفلاتر.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        @endif

        <!-- Document Footer -->
        <footer class="report-footer">
            <div>
                <div><strong>منصة التفوق التعليمية</strong> — تقرير إداري وأكاديمي نظامي معتمد.</div>
                <div style="font-size: 10px; color: #94a3b8; margin-top: 4px;">تم توليد هذا المستند آلياً عبر نظام إدارة التعليم الإلكتروني.</div>
            </div>
            <div class="stamp-box">
                <div class="stamp-title">اعتماد الإدارة الأكاديمية</div>
                <div style="color: #cbd5e1; font-size: 10px;">التوقيع والختم الرسمي</div>
            </div>
        </footer>

    </div>

    @if (request('auto') == 1)
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.print();
            }, 600);
        });
    </script>
    @endif

</body>
</html>
