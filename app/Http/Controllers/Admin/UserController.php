<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\User\Services\ParentStudentLinkService;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Rules\AltafawwuqEmail;
use App\Rules\PhoneNumber;
use App\Services\AuditLogger;
use App\Services\ImageUploadService;
use App\Support\PhoneNumber as PhoneNumberValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly ParentStudentLinkService $parentStudentLinks,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role'   => ['nullable', 'string', 'in:admin,teacher,student,parent'],
        ]);

        $users = User::with('roles:name')
            ->when(! empty($filters['search']), function ($q) use ($filters): void {
                $search = '%' . $filters['search'] . '%';

                $q->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search);
                });
            })
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
        $request->merge([
            'email' => AltafawwuqEmail::normalize($request->input('email')),
            'phone' => PhoneNumberValue::normalize($request->input('phone')),
            'parent_phone' => PhoneNumberValue::normalize($request->input('parent_phone')),
        ]);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'lowercase', 'email', 'max:255', new AltafawwuqEmail(), 'unique:users'],
            'phone'       => ['required', 'string', 'min:7', 'max:20', new PhoneNumber(), 'unique:users'],
            'parent_phone' => ['exclude_unless:role,student', 'required', 'string', 'min:7', 'max:20', new PhoneNumber(), 'different:phone'],
            'password'    => ['required', 'string', 'min:8', 'max:255'],
            'role'        => ['required', 'string', 'in:admin,teacher,student,parent'],
            'grade_level' => [
                'required_if:role,student',
                'exclude_unless:role,student',
                'required',
                Rule::exists('grade_levels', 'key')
                    ->where(fn ($query) => $query
                        ->where('is_active', true)
                        ->where('key', '!=', 'all')),
            ],
        ], [
            'grade_level.required_if' => 'اختر المرحلة والصف الدراسي للطالب.',
            'grade_level.exists' => 'الصف الدراسي المختار غير متاح حالياً.',
        ]);

        $user = DB::transaction(function () use ($validated): User {
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

            if ($validated['role'] === 'student') {
                $this->parentStudentLinks->linkExistingParent($user, $validated['parent_phone']);
            }

            return $user;
        });

        return back()->with('success', "تم إضافة المستخدم {$user->name} بنجاح.");
    }

    public function resetPassword(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', Password::defaults()->max(255), 'confirmed'],
        ]);

        $user = User::findOrFail($id);
        $user->update(['password' => Hash::make($validated['password'])]);
        AuditLogger::record('admin.user.password_reset', $user);

        return back()->with('success', "تم تعيين كلمة مرور جديدة للمستخدم {$user->name}. لا يتم عرض كلمات المرور الحالية.");
    }

    public function toggleActive(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Prevent admin from deactivating themselves
        abort_if($user->id === Auth::id(), 403, 'لا يمكنك تعطيل حسابك الخاص.');

        $user->update(['is_active' => ! $user->is_active]);
        AuditLogger::record('admin.user.status_changed', $user, [
            'is_active' => $user->is_active,
        ]);

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

        $oldRole = $user->getRoleNames()->first();
        $user->syncRoles([$validated['role']]);
        if ($validated['role'] === 'teacher' && $user->commission_percent === null) {
            $user->update(['commission_percent' => (int) (\App\Domain\Settings\Models\PlatformSetting::where('key', 'commission_percent')->value('value') ?? 20)]);
        }

        AuditLogger::record('admin.user.role_changed', $user, [
            'old_role' => $oldRole,
            'new_role' => $validated['role'],
        ]);

        return back()->with('success', "تم تحديث دور {$user->name} بنجاح.");
    }

    public function updateCommission(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'commission_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);
        $user = User::findOrFail($id);
        abort_unless($user->hasRole('teacher'), 422, 'نسبة العمولة تُحدد للمدرسين فقط.');
        $oldCommission = $user->commission_percent;
        $user->update(['commission_percent' => $validated['commission_percent']]);
        AuditLogger::record('admin.user.commission_changed', $user, [
            'old_percent' => $oldCommission,
            'new_percent' => $validated['commission_percent'],
        ]);

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

        $this->deleteStoredImage($previous);
        $this->forgetTeacherCaches();

        return back()->with('success', "تم تحديث صورة المعلم {$user->name}.");
    }

    public function deleteAvatar(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        abort_unless($user->hasRole('teacher'), 422, 'تُدار الصور من هنا للمعلمين فقط.');

        $this->deleteStoredImage($user->avatar);
        $user->update(['avatar' => null]);
        $this->forgetTeacherCaches();

        return back()->with('success', "تم حذف صورة المعلم {$user->name}.");
    }

    /** Set the optional profile cover shown on the public teacher profile. */
    public function updateCover(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'profile_cover' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'profile_cover.max' => 'حجم الكافر يجب ألا يتجاوز 8 ميجابايت.',
        ]);

        $user = User::findOrFail($id);

        abort_unless($user->hasRole('teacher'), 422, 'تُدار كافرات البروفايل للمعلمين فقط.');

        $previous = $user->profile_cover;

        try {
            $user->update([
                'profile_cover' => ImageUploadService::uploadAndConvertToWebp(
                    $request->file('profile_cover'),
                    'teacher-covers',
                ),
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'تعذّرت معالجة الكافر. تأكد أن الملف صورة صالحة.');
        }

        $this->deleteStoredImage($previous);

        return back()->with('success', "تم تحديث كافر بروفايل المعلم {$user->name}.");
    }

    public function deleteCover(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        abort_unless($user->hasRole('teacher'), 422, 'تُدار كافرات البروفايل للمعلمين فقط.');

        $this->deleteStoredImage($user->profile_cover);
        $user->update(['profile_cover' => null]);

        return back()->with('success', "تم حذف كافر بروفايل المعلم {$user->name}.");
    }

    /** User images are stored on the public disk under a /storage/ prefix. */
    private function deleteStoredImage(?string $path): void
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

