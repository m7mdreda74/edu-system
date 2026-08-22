<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Http\Controllers\Controller;
use App\Services\ImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Subjects are the platform's curriculum. Each one is attached to the grades it
 * is taught at — Arabic runs the whole way through, physics only from grade 10.
 */
class SubjectController extends Controller
{
    public function index(): Response
    {
        $subjects = Subject::with('gradeLevels:id,key,name,stage,track')
            ->withCount('teachingAssignments')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Subjects', [
            'subjects'    => $subjects,
            'gradeLevels' => GradeLevel::where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'key', 'name', 'stage', 'track']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $image = null;
        if ($request->hasFile('image')) {
            try {
                $image = ImageUploadService::uploadAndConvertToWebp($request->file('image'), 'subjects');
            } catch (\Throwable) {
                return back()->withInput()->with('error', 'تعذّرت معالجة صورة المادة. تأكد أن الملف صورة صالحة.');
            }
        }

        $subject = Subject::create([...$this->attributes($validated), 'image' => $image]);
        $subject->gradeLevels()->sync($validated['grade_level_ids']);
        Cache::forget('home.featured_teachers');

        return back()->with('success', 'تم إضافة المادة الدراسية بنجاح.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $subject   = Subject::findOrFail($id);
        $validated = $this->validated($request, $subject->id);

        $previousImage = $subject->image;
        $newImage = null;
        if ($request->hasFile('image')) {
            try {
                $newImage = ImageUploadService::uploadAndConvertToWebp($request->file('image'), 'subjects');
            } catch (\Throwable) {
                return back()->withInput()->with('error', 'تعذّرت معالجة صورة المادة. تأكد أن الملف صورة صالحة.');
            }
        }

        $attributes = $this->attributes($validated);
        if ($newImage !== null) {
            $attributes['image'] = $newImage;
        } elseif ($request->boolean('remove_image')) {
            $attributes['image'] = null;
        }

        $subject->update($attributes);
        $subject->gradeLevels()->sync($validated['grade_level_ids']);

        if ($newImage !== null || $request->boolean('remove_image')) {
            $this->deleteStoredImage($previousImage);
        }
        Cache::forget('home.featured_teachers');

        return back()->with('success', 'تم تحديث المادة الدراسية بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $subject = Subject::findOrFail($id);

        if ($subject->teachingAssignments()->exists()) {
            return back()->with('error', 'لا يمكن حذف المادة لأنها مسندة إلى معلمين.');
        }

        $this->deleteStoredImage($subject->image);
        $subject->delete();
        Cache::forget('home.featured_teachers');

        return back()->with('success', 'تم حذف المادة الدراسية بنجاح.');
    }

    // ─── Internals ────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'              => ['required', 'string', 'max:255', 'unique:subjects,name' . ($ignoreId ? ",{$ignoreId}" : '')],
            'name_en'           => ['nullable', 'string', 'max:255'],
            'icon'              => ['nullable', 'string', 'max:50'],
            'image'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image'      => ['sometimes', 'boolean'],
            'is_active'         => ['sometimes', 'boolean'],
            'grade_level_ids'   => ['required', 'array', 'min:1', 'max:100'],
            'grade_level_ids.*' => ['integer', 'min:1', 'distinct', 'exists:grade_levels,id'],
        ]);
    }

    /** @return array<string, mixed> */
    private function attributes(array $validated): array
    {
        return [
            'name'      => $validated['name'],
            'name_en'   => $validated['name_en'] ?? null,
            'icon'      => $validated['icon'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ];
    }

    /** Images uploaded from the dashboard are stored on the public disk. */
    private function deleteStoredImage(?string $path): void
    {
        if (! $path || ! str_starts_with($path, '/storage/')) {
            return;
        }

        Storage::disk('public')->delete(substr($path, strlen('/storage/')));
    }
}
