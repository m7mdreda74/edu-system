<?php

declare(strict_types=1);

namespace App\Domain\Communication\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NotificationPreference extends Model
{
    public const CHANNEL_DATABASE = 'database';

    public const CHANNEL_MAIL = 'mail';

    protected $fillable = [
        'user_id',
        'notification_type',
        'channel',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<int, array{key:string,label:string,description:string,channels:array<int,string>}> */
    public static function catalog(): array
    {
        return [
            ['key' => 'generic_database_notification', 'label' => 'الإشعارات العامة', 'description' => 'التنبيهات العامة ورسائل المنصة.', 'channels' => [self::CHANNEL_DATABASE]],
            ['key' => 'academic_alert', 'label' => 'التنبيهات الأكاديمية', 'description' => 'الواجبات والحضور وتراجع الأداء والاشتراك.', 'channels' => [self::CHANNEL_DATABASE]],
            ['key' => 'live_session_reminder', 'label' => 'تذكيرات الحصص المباشرة', 'description' => 'التذكير بالحصة القادمة ومواعيدها.', 'channels' => [self::CHANNEL_DATABASE]],
            ['key' => 'subscription_renewal_reminder', 'label' => 'تذكيرات تجديد الاشتراك', 'description' => 'التنبيه عند اقتراب انتهاء الحصص الحالية.', 'channels' => [self::CHANNEL_DATABASE]],
            ['key' => 'payment_success', 'label' => 'تأكيدات الدفع', 'description' => 'تأكيد نجاح عمليات الدفع والفواتير.', 'channels' => [self::CHANNEL_DATABASE, self::CHANNEL_MAIL]],
            ['key' => 'payment_receipt_rejected', 'label' => 'إيصالات الدفع المرفوضة', 'description' => 'التنبيه عند رفض إيصال تحويل.', 'channels' => [self::CHANNEL_DATABASE]],
            ['key' => 'subscription_activated', 'label' => 'تفعيل الاشتراك', 'description' => 'التنبيه عند تفعيل الاشتراك الدراسي.', 'channels' => [self::CHANNEL_DATABASE]],
            ['key' => 'new_chat_message_notification', 'label' => 'رسائل المحادثة', 'description' => 'التنبيه عند وصول رسالة محادثة جديدة.', 'channels' => [self::CHANNEL_DATABASE]],
            ['key' => 'other', 'label' => 'إشعارات أخرى', 'description' => 'إشعارات الإدارة والحجز والحضور والمدرسين.', 'channels' => [self::CHANNEL_DATABASE]],
        ];
    }

    /** @return array<int, array<string,mixed>> */
    public static function catalogFor(User $user): array
    {
        $saved = $user->notificationPreferences()
            ->get(['notification_type', 'channel', 'is_enabled'])
            ->keyBy(fn (self $preference): string => $preference->notification_type.'|'.$preference->channel);

        return array_map(function (array $type) use ($saved): array {
            $type['channels'] = array_map(function (string $channel) use ($type, $saved): array {
                $key = $type['key'].'|'.$channel;

                return [
                    'key' => $channel,
                    'label' => $channel === self::CHANNEL_MAIL ? 'البريد الإلكتروني' : 'داخل المنصة',
                    'enabled' => $saved->get($key)?->is_enabled ?? true,
                ];
            }, $type['channels']);

            return $type;
        }, self::catalog());
    }

    public static function typeFor(object $notification, object $notifiable): string
    {
        $payload = method_exists($notification, 'toArray')
            ? $notification->toArray($notifiable)
            : [];

        if (empty($payload) && method_exists($notification, 'toDatabase')) {
            $payload = $notification->toDatabase($notifiable);
        }

        $type = is_array($payload) ? ($payload['type'] ?? null) : null;

        $resolved = is_string($type) && $type !== ''
            ? $type
            : Str::snake(class_basename($notification));

        return in_array($resolved, array_column(self::catalog(), 'key'), true)
            ? $resolved
            : 'other';
    }

    public static function supports(string $type, string $channel): bool
    {
        foreach (self::catalog() as $item) {
            if ($item['key'] === $type) {
                return in_array($channel, $item['channels'], true);
            }
        }

        return false;
    }

    public static function enabledFor(User $user, string $type, string $channel): bool
    {
        if (! self::supports($type, $channel)) {
            return true;
        }

        return $user->notificationPreferences()
            ->where('notification_type', $type)
            ->where('channel', $channel)
            ->value('is_enabled') ?? true;
    }
}
