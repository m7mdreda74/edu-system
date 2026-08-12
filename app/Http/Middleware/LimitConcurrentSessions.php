<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LimitConcurrentSessions
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('session.driver') !== 'database') {
            return $next($request);
        }

        $user = $request->user();

        if ($user) {
            // Fetch only enough rows to decide whether an older session must
            // be removed; the deletion remains scoped to this user.
            $sessionIds = DB::table('sessions')
                ->where('user_id', $user->id)
                ->orderBy('last_activity', 'desc')
                ->limit(3)
                ->pluck('id');

            // If sessions exceed 2, delete the older ones
            if ($sessionIds->count() > 2) {
                $sessionsToKeep = $sessionIds->take(2)->all();

                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->whereNotIn('id', $sessionsToKeep)
                    ->delete();
            }
        }

        return $next($request);
    }
}
