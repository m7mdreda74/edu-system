<?php

namespace App\Http\Controllers;

use App\Domain\Communication\Models\NotificationPreference;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ImageUploadService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'canChangePassword' => ! $request->user()->hasRole('teacher'),
            'isStudent' => $request->user()->hasRole('student'),
            'notificationPreferences' => NotificationPreference::catalogFor($request->user()),
        ]);
    }

    public function updateNotificationPreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.type' => ['required', 'string', Rule::in(array_column(NotificationPreference::catalog(), 'key'))],
            'preferences.*.channel' => ['required', 'string', Rule::in([NotificationPreference::CHANNEL_DATABASE, NotificationPreference::CHANNEL_MAIL])],
            'preferences.*.enabled' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            foreach ($validated['preferences'] as $preference) {
                if (! NotificationPreference::supports($preference['type'], $preference['channel'])) {
                    continue;
                }

                $request->user()->notificationPreferences()->updateOrCreate(
                    [
                        'notification_type' => $preference['type'],
                        'channel' => $preference['channel'],
                    ],
                    ['is_enabled' => (bool) $preference['enabled']],
                );
            }
        });

        return Redirect::route('profile.edit')->with('success', 'تم حفظ تفضيلات الإشعارات.');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // A student's account data (name, email, photo, grade) is managed by
        // the platform admin only.
        abort_if($request->user()->hasRole('student'), 403, 'بيانات حساب الطالب تُدار من قبل إدارة المنصة فقط.');

        $user = $request->user();
        $user->fill($request->safe()->except('avatar'));

        if ($request->hasFile('avatar')) {
            // Delete old avatar if it exists
            if ($user->avatar) {
                $oldPath = str_replace('/storage/', '', $user->avatar);
                Storage::disk('public')->delete($oldPath);
            }
            $user->avatar = ImageUploadService::uploadAndConvertToWebp($request->file('avatar'), 'avatars');
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Students cannot delete their own account; only an admin can.
        abort_if($request->user()->hasRole('student'), 403, 'حذف حساب الطالب يتم من قبل إدارة المنصة فقط.');

        $request->validate([
            'password' => ['required', 'string', 'max:255', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
