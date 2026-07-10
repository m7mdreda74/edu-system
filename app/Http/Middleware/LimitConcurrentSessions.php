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
        $user = $request->user();

        if ($user) {
            // Get all active sessions for this user ordered by last activity
            $sessions = DB::table('sessions')
                ->where('user_id', $user->id)
                ->orderBy('last_activity', 'desc')
                ->get();

            // If sessions exceed 2, delete the older ones
            if ($sessions->count() > 2) {
                $sessionsToKeep = $sessions->take(2)->pluck('id')->toArray();
                
                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->whereNotIn('id', $sessionsToKeep)
                    ->delete();
            }
        }

        return $next($request);
    }
}
