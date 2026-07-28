<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Services\ImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role'   => ['nullable', 'string', 'in:admin,teacher,student'],
        ]);

        $users = User::with('roles:name')
            ->when(! empty($filters['search']), fn ($q) =>
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%')
            )
            ->when(! empty($filters['role']), fn ($q) =>
                $q->role($filters['role'])
            )
            ->withCount('subscriptions')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Users', [
            'users'   => $users,
            'filters' => $filters,
            'defaultCommission' => (int) (\App\Domain\Settings\Models\PlatformSetting::where('key', 'commission_percent')->value('value') ?? 20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'phone'       => ['required', 'string', 'max:20', 'unique:users'],
            'password'    => ['required', 'string', 'min:8'],
            'role'        => ['required', 'string', 'in:admin,teacher,student,parent'],
            'grade_level' => ['nullable', 'exists:grade_levels,key'],
        ]);

        $user = User::create([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'phone'       => $validated['phone'],
            'password'    => $validated['password'], // hashed by cast
            'grade_level' => ($validated['role'] === 'student') ? ($validated['grade_level'] ?? null) : null,
            'is_active'   => true,
            'commission_percent' => $validated['role'] === 'teacher'
                ? (int) (\App\Domain\Settings\Models\PlatformSetting::where('key', 'commission_percent')->value('value') ?? 20)
                : null,
        ]);

        $user->assignRole($validated['role']);

        return back()->with('success', "تم إضافة المستخدم {$user->name} بنجاح.");
    }

    public function toggleActive(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Prevent admin from deactivating themselves
        abort_if($user->id === Auth::id(), 403, 'لا يمكنك تعطيل حسابك الخاص.');

        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'تفعيل' : 'تعطيل';

        return back()->with('success', "تم {$status} حساب {$user->name}.");
    }

    public function updateRole(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:admin,teacher,student,parent'],
        ]);

        $user = User::findOrFail($id);

        // Prevent admin from changing their own role
        abort_if($user->id === Auth::id(), 403, 'لا يمكنك تغيير دورك الخاص.');

        $user->syncRoles([$validated['role']]);
        if ($validated['role'] === 'teacher' && $user->commission_percent === null) {
            $user->update(['commission_percent' => (int) (\App\Domain\Settings\Models\PlatformSetting::where('key', 'commission_percent')->value('value') ?? 20)]);
        }

        return back()->with('success', "تم تحديث دور {$user->name} بنجاح.");
    }

    public function updateCommission(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'commission_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);
        $user = User::findOrFail($id);
        abort_unless($user->hasRole('teacher'), 422, 'نسبة العمولة تُحدد للمدرسين فقط.');
        $user->update(['commission_percent' => $validated['commission_percent']]);

        return back()->with('success', "تم تحديث نسبة عمولة المدرس {$user->name}.");
    }

    /**
     * Set a teacher's photo.
     *
     * Teachers cannot set their own: the photo is what a visitor judges on the
     * browse pages, so the platform keeps it consistent and on-brand.
     */
    public function updateAvatar(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'avatar.max' => 'حجم الصورة يجب ألا يتجاوز 4 ميجابايت.',
        ]);

        $user = User::findOrFail($id);

        abort_unless($user->hasRole('teacher'), 422, 'تُدار الصور من هنا للمعلمين فقط.');

        $previous = $user->avatar;

        try {
            $user->update([
                'avatar' => ImageUploadService::uploadAndConvertToWebp($request->file('avatar'), 'avatars'),
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'تعذّرت معالجة الصورة. تأكد أن الملف صورة صالحة.');
        }

        $this->deleteStoredAvatar($previous);
        $this->forgetTeacherCaches();

        return back()->with('success', "تم تحديث صورة المعلم {$user->name}.");
    }

    public function deleteAvatar(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        abort_unless($user->hasRole('teacher'), 422, 'تُدار الصور من هنا للمعلمين فقط.');

        $this->deleteStoredAvatar($user->avatar);
        $user->update(['avatar' => null]);
        $this->forgetTeacherCaches();

        return back()->with('success', "تم حذف صورة المعلم {$user->name}.");
    }

    /** Avatars are stored on the public disk under a /storage/ prefix. */
    private function deleteStoredAvatar(?string $path): void
    {
        if (! $path || ! str_starts_with($path, '/storage/')) {
            return;
        }

        Storage::disk('public')->delete(substr($path, strlen('/storage/')));
    }

    /** Teacher photos are baked into the cached home-page cards. */
    private function forgetTeacherCaches(): void
    {
        Cache::forget('home.featured_teachers');
    }
}

