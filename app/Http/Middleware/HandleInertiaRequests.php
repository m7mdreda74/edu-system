<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Settings\Models\PlatformSetting;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Throwable;

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
                ] : null,
            ],

            // Flash messages (success/error from controller redirects)
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],

            // Dynamic platform settings (cached)
            'settings' => PlatformSetting::getAllCached(),

            // Active grade levels
            'grade_levels' => \App\Domain\Course\Models\GradeLevel::where('is_active', true)
                ->select('id', 'key', 'name', 'name_en')
                ->get(),

            // Environment info for admin panel
            'env' => app()->environment(),
        ];
    }
}

