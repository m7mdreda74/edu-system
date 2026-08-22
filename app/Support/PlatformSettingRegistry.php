<?php

declare(strict_types=1);

namespace App\Support;

use JsonException;

/**
 * The only settings that can be written through the admin CMS.
 *
 * Settings are rendered on public pages, so accepting arbitrary keys or HTML
 * here turns a legitimate admin form into a persistence/XSS boundary. Keep
 * the registry deliberately boring: every key has one expected type and the
 * few structured values are JSON arrays without HTML.
 */
final class PlatformSettingRegistry
{
    private const TEXT_KEYS = [
        'platform_name',
        'contact_email',
        'contact_phone',
        'contact_badge',
        'contact_title',
        'platform_email',
        'footer_desc',
        'welcome_popup_title',
        'welcome_popup_bottom_label',
        'welcome_popup_item1_label',
        'welcome_popup_item2_label',
        'welcome_popup_item3_label',
        'welcome_popup_item4_label',
        'welcome_popup_item5_label',
        'welcome_popup_item6_label',
        'home_hero_badge',
        'home_hero_title',
        'home_hero_subtitle',
        'home_hero_desc',
        'home_hero_btn1',
        'home_hero_btn2',
        'home_cta_title',
        'home_cta_desc',
        'home_cta_btn',
        'about_title',
        'about_badge',
        'about_desc',
        'app_title',
        'app_badge',
        'app_desc',
    ];

    private const URL_KEYS = [
        'whatsapp_url',
        'welcome_popup_bottom_url',
        'welcome_popup_item1_url',
        'welcome_popup_item2_url',
        'welcome_popup_item3_url',
        'welcome_popup_item4_url',
        'welcome_popup_item5_url',
        'welcome_popup_item6_url',
        'app_win_url',
        'app_mac_url',
        'app_ios_url',
        'app_android_url',
        'app_huawei_url',
    ];

    private const EMAIL_KEYS = [
        'contact_email',
        'platform_email',
    ];

    private const JSON_KEYS = [
        'home_features',
        'home_results',
        'home_why_choose_us',
        'home_youtube_videos',
        'home_faqs',
        'about_values',
        'about_pillars',
        'navbar_links',
        'footer_links',
        'footer_social_links',
    ];

    private const BOOLEAN_KEYS = [
        'registration_open',
        'welcome_popup_active',
        'home_youtube_visible',
    ];

    private const INTEGER_KEYS = [
        'commission_percent',
    ];

    private const ENUMERATED_KEYS = [
        'site_theme' => ['royal', 'ocean', 'emerald', 'violet'],
    ];

    /** @var array<string, array{type: string, max: int, site_page: bool}>|null */
    private static ?array $definitions = null;

    /** @return array<string, array{type: string, max: int, site_page: bool}> */
    public static function definitions(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $definitions = [];

        foreach (self::TEXT_KEYS as $key) {
            $definitions[$key] = ['type' => 'string', 'max' => 10_000, 'site_page' => true];
        }

        foreach (self::URL_KEYS as $key) {
            $definitions[$key] = ['type' => 'string', 'max' => 2_048, 'site_page' => true];
        }

        foreach (self::JSON_KEYS as $key) {
            $definitions[$key] = ['type' => 'string', 'max' => 100_000, 'site_page' => true];
        }

        foreach (self::BOOLEAN_KEYS as $key) {
            $definitions[$key] = ['type' => 'boolean', 'max' => 5, 'site_page' => true];
        }

        foreach (self::INTEGER_KEYS as $key) {
            $definitions[$key] = ['type' => 'integer', 'max' => 3, 'site_page' => false];
        }

        foreach (self::EMAIL_KEYS as $key) {
            $definitions[$key]['max'] = 255;
        }

        $definitions['contact_phone']['max'] = 25;
        $definitions['site_theme'] = ['type' => 'string', 'max' => 20, 'site_page' => false];

        return self::$definitions = $definitions;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    public static function isKnown(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    public static function isSitePageKey(string $key): bool
    {
        return self::definitions()[$key]['site_page'] ?? false;
    }

    public static function expectedType(string $key): ?string
    {
        return self::definitions()[$key]['type'] ?? null;
    }

    /**
     * Return a human-readable validation message, or null when the value is
     * safe for the registered key.
     */
    public static function validate(string $key, mixed $value, ?string $providedType = null): ?string
    {
        $definition = self::definitions()[$key] ?? null;

        if ($definition === null) {
            return 'مفتاح الإعداد غير مسموح.';
        }

        if ($providedType !== null && $providedType !== $definition['type']) {
            return 'نوع الإعداد لا يطابق المخطط المعتمد.';
        }

        if ($definition['type'] === 'integer') {
            if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                return 'يجب أن تكون قيمة الإعداد رقماً صحيحاً.';
            }

            if ($key === 'commission_percent' && ((int) $value < 0 || (int) $value > 100)) {
                return 'نسبة العمولة يجب أن تكون بين 0 و100.';
            }

            return null;
        }

        if ($definition['type'] === 'boolean') {
            return in_array($value, [true, false, 'true', 'false', '0', '1', 0, 1], true)
                ? null
                : 'يجب أن تكون قيمة الإعداد true أو false.';
        }

        if (in_array($key, self::URL_KEYS, true)) {
            if ($value === null || $value === '') {
                return null;
            }

            if (! is_string($value) || mb_strlen($value) > $definition['max'] || ! self::isSafeUrl($value)) {
                return 'الرابط غير صالح أو غير مسموح.';
            }

            return null;
        }

        if (in_array($key, self::JSON_KEYS, true)) {
            if (is_array($value)) {
                if (self::containsUnsafeMarkup($value)) {
                    return 'المحتوى HTML غير مسموح.';
                }

                return self::containsUnsafeStructuredUrl($value)
                    ? 'يوجد رابط غير مسموح داخل البيانات.'
                    : null;
            }

            if (! is_string($value) || mb_strlen($value) > $definition['max']) {
                return 'بيانات JSON غير صالحة أو أكبر من الحجم المسموح.';
            }

            try {
                $decoded = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return 'يجب أن تكون القيمة JSON صحيحة.';
            }

            if (! is_array($decoded) || self::containsUnsafeMarkup($decoded)) {
                return 'يجب أن تكون القيمة مصفوفة JSON خالية من HTML.';
            }

            return self::containsUnsafeStructuredUrl($decoded)
                ? 'يوجد رابط غير مسموح داخل البيانات.'
                : null;
        }

        if (! is_string($value) || mb_strlen($value) > $definition['max']) {
            return 'قيمة الإعداد طويلة أو غير صالحة.';
        }

        if (in_array($key, self::EMAIL_KEYS, true) && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return 'البريد الإلكتروني غير صالح.';
        }

        if (self::containsUnsafeMarkup($value)) {
            return 'HTML غير مسموح داخل إعدادات المحتوى.';
        }

        if (isset(self::ENUMERATED_KEYS[$key]) && ! in_array($value, self::ENUMERATED_KEYS[$key], true)) {
            return 'القيمة المختارة غير متاحة.';
        }

        return null;
    }

    public static function isSafeUrl(string $value): bool
    {
        if (preg_match('/[\x00-\x20<>"\']/', $value) === 1) {
            return false;
        }

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return true;
        }

        if (str_starts_with($value, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)
            && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private static function containsUnsafeMarkup(mixed $value): bool
    {
        if (is_string($value)) {
            return strip_tags($value) !== $value
                || preg_match('/<\s*(script|iframe|object|embed|style|svg)\b|\bon[a-z]+\s*=/iu', $value) === 1;
        }

        if (is_array($value)) {
            foreach ($value as $child) {
                if (self::containsUnsafeMarkup($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function containsUnsafeStructuredUrl(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $key => $child) {
            if (is_string($child) && is_string($key) && preg_match('/(?:url|href|thumbnail)$/i', $key) === 1) {
                if ($child !== '' && ! self::isSafeUrl($child)) {
                    return true;
                }
            }

            if (self::containsUnsafeStructuredUrl($child)) {
                return true;
            }
        }

        return false;
    }
}
