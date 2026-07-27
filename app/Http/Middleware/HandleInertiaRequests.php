<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Settings\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Shared props available on EVERY Vue page via usePage().props
     * Keep this lean — only data needed on every request.
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id'          => $user->id,
                    'name'        => $user->name,
                    'email'       => $user->email,
                    'avatar'      => $user->avatar,
                    'grade_level' => $user->grade_level,
                    'roles'       => $user->getRoleNames()->toArray(),

                    // Public teacher profile — needed by the profile form.
                    'headline'              => $user->headline,
                    'bio'                   => $user->bio,
                    'intro_video_url'       => $user->intro_video_url,
                    'intro_video_thumbnail' => $user->intro_video_thumbnail,
                    'years_experience'      => $user->years_experience,
                ] : null,
            ],

            // Flash messages (success/error from controller redirects)
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],

            // Dynamic platform settings (cached)
            'settings' => fn () => PlatformSetting::getAllCached(),

            'grade_levels' => fn () => Cache::remember(
                'shared.active_grade_levels',
                now()->addHour(),
                fn () => GradeLevel::where('is_active', true)
                    ->select('id', 'key', 'name', 'name_en', 'stage', 'track')
                    ->get()
                    ->all(),
            ),

            // Environment info for admin panel
            'env' => app()->environment(),
        ];
    }
}
