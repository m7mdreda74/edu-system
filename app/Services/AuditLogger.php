<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

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
        if (! Schema::hasTable('audit_events')) {
            // Local and test environments may boot before migrations run, but
            // production must never silently lose security/audit evidence.
            if (app()->environment('production')) {
                throw new RuntimeException(
                    'Audit logging is unavailable; apply the audit_events migration before enabling sensitive mutations.',
                );
            }

            return null;
        }

        $request = app()->bound('request') ? request() : null;
        $actor = $actorOverride ?? auth()->user();

        $adminName = null;
        $adminEmail = null;
        if ($actor instanceof \App\Domain\User\Models\User) {
            $adminName = $actor->name;
            $adminEmail = $actor->email;
        } elseif (is_object($actor) && isset($actor->name)) {
            $adminName = (string) $actor->name;
            $adminEmail = isset($actor->email) ? (string) $actor->email : null;
        }

        $mergedMetadata = array_merge([
            'admin_name' => $adminName,
            'admin_email' => $adminEmail,
        ], $metadata);

        return AuditEvent::create([
            'actor_id' => $actor?->getAuthIdentifier(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass() ?? ($auditable ? $auditable::class : null),
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $mergedMetadata,
            'created_at' => now(),
        ]);
    }

    public static function hashValue(mixed $value): ?string
    {
        return $value === null ? null : hash('sha256', (string) $value);
    }

    public static function humanDescription(AuditEvent $event): string
    {
        $meta = $event->metadata ?? [];
        $actor = $meta['admin_name'] ?? $event->actor?->name ?? 'مسؤول';

        return match ($event->action) {
            'admin.payment.approved' => "قام {$actor} باعتماد دفعة مالية" . (! empty($meta['student_name']) ? " للطالب {$meta['student_name']}" : ''),
            'admin.payment.rejected' => "قام {$actor} برفض إيصال تحويل" . (! empty($meta['student_name']) ? " للطالب {$meta['student_name']}" : ''),
            'admin.payment.receipt_accessed' => "قام {$actor} بعرض إيصال تحويل",
            'admin.user.created' => "قام {$actor} بإنشاء حساب " . ($meta['role'] ?? 'مستخدم') . (! empty($meta['user_name']) ? ": {$meta['user_name']}" : ''),
            'admin.user.status_changed' => "قام {$actor} بتعديل حالة الحساب" . (! empty($meta['target_name']) ? " للمستخدم {$meta['target_name']}" : ''),
            'admin.user.role_changed' => "قام {$actor} بتغيير دور المستخدم" . (! empty($meta['target_name']) ? " {$meta['target_name']}" : ''),
            'admin.user.grade_changed' => "قام {$actor} بتعديل الصف الدراسي" . (! empty($meta['target_name']) ? " للطالب {$meta['target_name']}" : ''),
            'admin.user.commission_changed' => "قام {$actor} بتعديل نسبة عمولة المعلم" . (! empty($meta['target_name']) ? " {$meta['target_name']}" : ''),
            'admin.user.intro_video_updated' => "قام {$actor} برفع/تحديث الفيديو التعريفي للمعلم" . (! empty($meta['teacher_name']) ? " {$meta['teacher_name']}" : ''),
            'admin.user.intro_video_deleted' => "قام {$actor} بحذف الفيديو التعريفي للمعلم" . (! empty($meta['teacher_name']) ? " {$meta['teacher_name']}" : ''),
            'admin.user.password_reset' => "قام {$actor} بإعادة تعيين كلمة مرور المستخدم" . (! empty($meta['target_name']) ? " {$meta['target_name']}" : ''),
            'admin.user.deleted' => "قام {$actor} بحذف حساب المستخدم" . (! empty($meta['deleted_user_name']) ? " {$meta['deleted_user_name']}" : ''),
            'admin.payout.processed' => "قام {$actor} بتسوية/دفع أرباح المعلم" . (! empty($meta['teacher_name']) ? " {$meta['teacher_name']}" : ''),
            'admin.coupon.created' => "قام {$actor} بإنشاء كود الخصم" . (! empty($meta['code']) ? " {$meta['code']}" : ''),
            'admin.coupon.deleted' => "قام {$actor} بحذف كود الخصم" . (! empty($meta['code']) ? " {$meta['code']}" : ''),
            'admin.coupon.toggled' => "قام {$actor} بتغيير حالة تفعيل كود الخصم",
            'admin.group.created' => "قام {$actor} بإنشاء مجموعة دراسية" . (! empty($meta['name']) ? " {$meta['name']}" : ''),
            'admin.group.updated' => "قام {$actor} بتحديث مجموعة دراسية" . (! empty($meta['name']) ? " {$meta['name']}" : ''),
            'admin.group.deleted' => "قام {$actor} بحذف مجموعة دراسية",
            'settings.updated' => "قام {$actor} بتحديث إعدادات المنصة",
            'site_pages.updated' => "قام {$actor} بتحديث صفحات ومحتوى الموقع",
            'auth.login' => "تسجيل دخول {$actor}",
            'auth.logout' => "تسجيل خروج {$actor}",
            default => "إجراء إداري ({$event->action}) بواسطة {$actor}",
        };
    }
}
