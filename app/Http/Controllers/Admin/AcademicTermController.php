<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Academic\Models\AcademicTerm;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The school calendar. Terms ship with the ministry's published dates, but the
 * calendar is reissued every year, so the admin owns it from here.
 */
class AcademicTermController extends Controller
{
    public function index(): Response
    {
        $terms = AcademicTerm::withCount('groups')
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (AcademicTerm $term) => [
                'id'             => $term->id,
                'year_label'     => $term->year_label,
                'term_number'    => $term->term_number,
                'name'           => $term->name,
                'full_name'      => $term->fullName(),
                'starts_on'      => $term->starts_on?->toDateString(),
                'ends_on'        => $term->ends_on?->toDateString(),
                'is_provisional' => $term->is_provisional,
                'is_current'     => $term->isCurrent(),
                'groups_count'   => $term->groups_count,
                'weeks_remaining' => $term->weeksRemaining(),
            ]);

        return Inertia::render('Admin/AcademicTerms', [
            'terms'   => $terms,
            'current' => AcademicTerm::currentOrNext()?->fullName(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AcademicTerm::create($this->validated($request));
        Cache::forget('home.grades');

        return back()->with('success', 'تم إضافة الفصل الدراسي.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        AcademicTerm::findOrFail($id)->update($this->validated($request, $id));
        Cache::forget('home.grades');

        return back()->with('success', 'تم تحديث الفصل الدراسي.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $term = AcademicTerm::withCount('groups')->findOrFail($id);

        if ($term->groups_count > 0) {
            return back()->with('error', 'لا يمكن حذف فصل مرتبط بمجموعات تدريس.');
        }

        $term->delete();

        return back()->with('success', 'تم حذف الفصل الدراسي.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $unique = 'unique:academic_terms,term_number,' . ($ignoreId ?? 'NULL') . ',id,year_label,' . $request->input('year_label');

        return $request->validate([
            'year_label'     => ['required', 'string', 'max:20', 'regex:/^\d{4}\/\d{4}$/'],
            'term_number'    => ['required', 'integer', 'between:1,3', $unique],
            'name'           => ['required', 'string', 'max:255'],
            'starts_on'      => ['required', 'date'],
            'ends_on'        => ['required', 'date', 'after:starts_on'],
            'is_provisional' => ['sometimes', 'boolean'],
        ], [
            'year_label.regex'   => 'صيغة العام الدراسي يجب أن تكون مثل 2026/2027.',
            'term_number.unique' => 'هذا الفصل مسجّل بالفعل لنفس العام الدراسي.',
            'ends_on.after'      => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية.',
        ]);
    }
}
