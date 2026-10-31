<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\User\Services\ParentStudentLinkService;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Rules\PlatformEmail;
use App\Rules\PhoneNumber;
use App\Services\AuditLogger;
use App\Services\ImageUploadService;
use App\Support\PhoneNumber as PhoneNumberValue;
use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;
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

        $users = User::with(['roles:name', 'creator:id,name,email'])
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
            'email' => PlatformEmail::normalize($request->input('email')),
            'phone' => PhoneNumberValue::normalize($request->input('phone')),
            'parent_phone' => PhoneNumberValue::normalize($request->input('parent_phone')),
        ]);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'lowercase', 'email', 'max:255', new PlatformEmail(), 'unique:users'],
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
                'created_by'  => Auth::id(),
            ]);

            $user->assignRole($validated['role']);

            if ($validated['role'] === 'student') {
                $this->parentStudentLinks->linkExistingParent($user, $validated['parent_phone']);
            }

            return $user;
        });

        AuditLogger::record('admin.user.created', $user, [
            'role' => $validated['role'],
            'user_name' => $user->name,
            'user_email' => $user->email,
            'admin_name' => Auth::user()?->name,
        ]);

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

    public function updateGrade(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'grade_level' => [
                'required',
                'string',
                Rule::exists('grade_levels', 'key')
                    ->where(fn ($query) => $query
                        ->where('is_active', true)
                        ->where('key', '!=', 'all')),
            ],
        ], [
            'grade_level.exists' => 'الصف الدراسي المختار غير متاح حالياً.',
        ]);

        $user = User::findOrFail($id);
        abort_unless($user->hasRole('student'), 422, 'الصف الدراسي يخص حسابات الطلاب فقط.');

        $oldGrade = $user->grade_level;
        $user->update(['grade_level' => $validated['grade_level']]);

        AuditLogger::record('admin.user.grade_changed', $user, [
            'old_grade' => $oldGrade,
            'new_grade' => $validated['grade_level'],
        ]);

        return back()->with('success', "تم تحديث الصف الدراسي للطالب {$user->name}.");
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

    public function updateIntroVideo(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'intro_video_file' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/ogg,video/quicktime', 'max:102400'],
            'intro_video_url' => ['nullable', 'string', 'max:2048'],
            'intro_video_thumbnail_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'intro_video_thumbnail' => ['nullable', 'string', 'max:2048'],
            'delete_video' => ['nullable', 'boolean'],
            'delete_thumbnail' => ['nullable', 'boolean'],
        ], [
            'intro_video_file.mimetypes' => 'صيغة الفيديو يجب أن تكون MP4 أو WebM أو MOV.',
            'intro_video_file.max' => 'الحد الأقصى لحجم ملف الفيديو هو 100 ميجابايت.',
            'intro_video_thumbnail_file.image' => 'يجب أن يكون ملف الصورة المصغرة صورة صالحة.',
            'intro_video_thumbnail_file.max' => 'الحد الأقصى لحجم صورة الغلاف هو 8 ميجابايت.',
        ]);

        $user = User::findOrFail($id);
        abort_unless($user->hasRole('teacher'), 422, 'يُدار الفيديو التعريفي للمعلمين فقط.');

        $updates = [];

        // 1. Direct video file upload
        if ($request->hasFile('intro_video_file')) {
            $file = $request->file('intro_video_file');
            $extension = strtolower($file->getClientOriginalExtension() ?: 'mp4');
            $filename = uniqid('intro_video_') . '.' . $extension;
            $storedPath = Storage::disk('public')->putFileAs('teacher-intro-videos', $file, $filename);

            // Clean previous stored video if locally uploaded
            $this->deleteStoredImage($user->intro_video_url);

            $updates['intro_video_url'] = '/storage/' . $storedPath;
        } elseif ($request->filled('intro_video_url')) {
            $url = trim((string) $request->input('intro_video_url'));
            if ($user->intro_video_url !== $url) {
                $this->deleteStoredImage($user->intro_video_url);
            }
            $updates['intro_video_url'] = $url;
        } elseif ($request->boolean('delete_video')) {
            $this->deleteStoredImage($user->intro_video_url);
            $updates['intro_video_url'] = null;
        }

        // 2. Thumbnail image upload or URL
        if ($request->hasFile('intro_video_thumbnail_file')) {
            try {
                $thumbnailPath = ImageUploadService::uploadAndConvertToWebp(
                    $request->file('intro_video_thumbnail_file'),
                    'teacher-intro-thumbnails'
                );
                $this->deleteStoredImage($user->intro_video_thumbnail);
                $updates['intro_video_thumbnail'] = $thumbnailPath;
            } catch (\Throwable $e) {
                return back()->with('error', 'تعذّرت معالجة صورة الفيديو المصغرة. تأكد من سلامة صيغة الصورة.');
            }
        } elseif ($request->filled('intro_video_thumbnail')) {
            $updates['intro_video_thumbnail'] = trim((string) $request->input('intro_video_thumbnail'));
        } elseif ($request->boolean('delete_thumbnail')) {
            $this->deleteStoredImage($user->intro_video_thumbnail);
            $updates['intro_video_thumbnail'] = null;
        }

        if (! empty($updates)) {
            $user->update($updates);

            AuditLogger::record('admin.user.intro_video_updated', $user, [
                'intro_video_url' => $user->intro_video_url,
                'intro_video_thumbnail' => $user->intro_video_thumbnail,
            ]);

            $this->forgetTeacherCaches();
        }

        return back()->with('success', "تم تحديث ورفع الفيديو التعريفي للمعلم {$user->name} بنجاح.");
    }

    public function deleteIntroVideo(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        abort_unless($user->hasRole('teacher'), 422, 'يُدار الفيديو التعريفي للمعلمين فقط.');

        $this->deleteStoredImage($user->intro_video_url);
        $this->deleteStoredImage($user->intro_video_thumbnail);

        $user->update([
            'intro_video_url' => null,
            'intro_video_thumbnail' => null,
        ]);

        AuditLogger::record('admin.user.intro_video_deleted', $user);

        $this->forgetTeacherCaches();

        return back()->with('success', "تم حذف الفيديو التعريفي للمعلم {$user->name}.");
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

    public function auditLogs(int $id): JsonResponse
    {
        $user = User::with('creator:id,name,email')->findOrFail($id);

        $events = AuditEvent::with('actor:id,name,email')
            ->where(function ($query) use ($user): void {
                $query->where(function ($q) use ($user): void {
                    $q->where('auditable_type', $user->getMorphClass())
                        ->where('auditable_id', $user->id);
                })->orWhere(function ($q) use ($user): void {
                    $q->where('action', 'like', 'admin.user.%')
                        ->where('metadata->target_id', $user->id);
                });
            })
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (AuditEvent $e): array => [
                'id' => $e->id,
                'action' => $e->action,
                'description' => AuditLogger::humanDescription($e),
                'admin_name' => $e->metadata['admin_name'] ?? $e->actor?->name ?? 'مسؤول',
                'admin_email' => $e->metadata['admin_email'] ?? $e->actor?->email ?? null,
                'created_at' => $e->created_at?->toIso8601String(),
                'ip_address' => $e->ip_address,
                'metadata' => $e->metadata,
            ]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'creator' => $user->creator ? [
                    'id' => $user->creator->id,
                    'name' => $user->creator->name,
                    'email' => $user->creator->email,
                ] : null,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'events' => $events,
        ]);
    }
}

