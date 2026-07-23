<?php

declare(strict_types=1);

namespace App\Http\Controllers\Course;

use App\Domain\Course\Models\LiveSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class WebRtcSignalingController extends Controller
{
    // ─── How old signals we return on each poll ────────────────────────────────
    private const SIGNAL_TTL_SECONDS     = 30;
    private const PARTICIPANT_TTL_SECONDS = 10; // offline if no heartbeat for 10s

    /**
     * POST /live-sessions/{id}/webrtc/heartbeat
     * Register / renew presence. Returns current participant list.
     */
    public function heartbeat(Request $request, int $id): JsonResponse
    {
        $session = LiveSession::findOrFail($id);
        $user    = Auth::user();

        // Upsert participant
        DB::table('webrtc_participants')->upsert(
            [
                'live_session_id' => $session->id,
                'user_id'         => $user->id,
                'name'            => $user->name,
                'is_teacher'      => $session->teacher_id === $user->id,
                'last_seen_at'    => Carbon::now(),
                'created_at'      => Carbon::now(),
                'updated_at'      => Carbon::now(),
            ],
            ['live_session_id', 'user_id'],
            ['name', 'last_seen_at', 'updated_at']
        );

        // Clean up stale participants
        $cutoff = Carbon::now()->subSeconds(self::PARTICIPANT_TTL_SECONDS);
        DB::table('webrtc_participants')
            ->where('live_session_id', $session->id)
            ->where('last_seen_at', '<', $cutoff)
            ->delete();

        // Return active participants (excluding self)
        $participants = DB::table('webrtc_participants')
            ->where('live_session_id', $session->id)
            ->where('last_seen_at', '>=', $cutoff)
            ->where('user_id', '!=', $user->id)
            ->get(['user_id', 'name', 'is_teacher', 'last_seen_at']);

        return response()->json(['participants' => $participants]);
    }

    /**
     * POST /live-sessions/{id}/webrtc/signal
     * Send a WebRTC signal (offer / answer / ice-candidate / chat / etc.)
     */
    public function signal(Request $request, int $id): JsonResponse
    {
        $session = LiveSession::findOrFail($id);
        $user    = Auth::user();

        $validated = $request->validate([
            'type'       => 'required|string|max:30',
            'to_user_id' => 'nullable|integer|exists:users,id',
            'payload'    => 'required|array',
        ]);

        DB::table('webrtc_signals')->insert([
            'live_session_id' => $session->id,
            'from_user_id'    => $user->id,
            'to_user_id'      => $validated['to_user_id'] ?? null,
            'type'            => $validated['type'],
            'payload'         => json_encode($validated['payload']),
            'created_at'      => Carbon::now(),
        ]);

        // Cleanup old signals (keep DB tidy)
        DB::table('webrtc_signals')
            ->where('live_session_id', $session->id)
            ->where('created_at', '<', Carbon::now()->subSeconds(self::SIGNAL_TTL_SECONDS))
            ->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * GET /live-sessions/{id}/webrtc/poll?since=<unix_timestamp_ms>
     * Poll for signals addressed to me (or broadcast) since last check.
     */
    public function poll(Request $request, int $id): JsonResponse
    {
        $session = LiveSession::findOrFail($id);
        $user    = Auth::user();

        // `since` is a unix timestamp in milliseconds from client
        $since = $request->query('since');
        $sinceAt = $since
            ? Carbon::createFromTimestampMs((int) $since)
            : Carbon::now()->subSeconds(2);

        $signals = DB::table('webrtc_signals')
            ->where('live_session_id', $session->id)
            ->where('from_user_id', '!=', $user->id)  // don't get own signals
            ->where(function ($q) use ($user) {
                $q->whereNull('to_user_id')            // broadcast
                  ->orWhere('to_user_id', $user->id);  // addressed to me
            })
            ->where('created_at', '>=', $sinceAt)
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(function ($row) {
                return [
                    'id'          => $row->id,
                    'from'        => $row->from_user_id,
                    'to'          => $row->to_user_id,
                    'type'        => $row->type,
                    'payload'     => json_decode($row->payload, true),
                    'at'          => Carbon::parse($row->created_at)->getPreciseTimestamp(3),
                ];
            });

        return response()->json([
            'signals'    => $signals,
            'server_now' => Carbon::now()->getPreciseTimestamp(3),
        ]);
    }

    /**
     * POST /live-sessions/{id}/webrtc/leave
     * Remove participant from the room.
     */
    public function leave(int $id): JsonResponse
    {
        $session = LiveSession::findOrFail($id);
        $user    = Auth::user();

        // Send leave signal to everyone
        DB::table('webrtc_signals')->insert([
            'live_session_id' => $session->id,
            'from_user_id'    => $user->id,
            'to_user_id'      => null,
            'type'            => 'leave',
            'payload'         => json_encode(['userId' => $user->id]),
            'created_at'      => Carbon::now(),
        ]);

        // Remove from participants
        DB::table('webrtc_participants')
            ->where('live_session_id', $session->id)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['ok' => true]);
    }
}
