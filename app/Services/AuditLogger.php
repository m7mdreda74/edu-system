<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class AuditLogger
{
    /**
     * Write only the metadata supplied by the caller. Callers must pass hashes
     * for values such as settings and never plaintext passwords or secrets.
     */
    public static function record(
        string $action,
        ?Model $auditable = null,
        array $metadata = [],
        ?Authenticatable $actorOverride = null,
    ): ?AuditEvent
    {
        // This keeps older deployments bootable during a rolling migration. A
        // production deploy must apply the audit_events migration before
        // enabling sensitive mutations.
        if (! Schema::hasTable('audit_events')) {
            return null;
        }

        $request = app()->bound('request') ? request() : null;
        $actor = $actorOverride ?? auth()->user();

        return AuditEvent::create([
            'actor_id' => $actor?->getAuthIdentifier(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass() ?? ($auditable ? $auditable::class : null),
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    public static function hashValue(mixed $value): ?string
    {
        return $value === null ? null : hash('sha256', (string) $value);
    }
}
