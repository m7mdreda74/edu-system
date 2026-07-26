<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Page copy lives in `platform_settings` so admins can edit it, which means the
 * stored text wins over anything the Vue components fall back to. Earlier
 * migrations seeded that copy back when the platform sold courses, so the FAQ
 * still walked visitors through buying one.
 *
 * Rewrites the wording in place to match the booking flow. Phrase-based rather
 * than key-based, so it also catches copy an admin has since edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        $replacements = [
            // Whole answers whose meaning changed, not just their wording.
            'قم بتسجيل حساب مجاني كطالب، ثم اختر المادة أو الكورس المناسب واضغط على زر الاشتراك، حيث يمكنك الدفع بأمان وسهولة عبر بطاقتك الائتمانية أو بطاقة الخصم (Stripe).'
                => 'قم بتسجيل حساب مجاني كطالب، ثم اختر صفك فالمادة، شاهد الفيديو التعريفي لكل معلم، واضغط اشتراك مع من تناسبك طريقته. الاشتراك شهري ويمكنك الدفع بأمان عبر بطاقتك الائتمانية أو بطاقة الخصم.',
            'ما هي خطوات الاشتراك وشراء الكورسات؟' => 'ما هي خطوات الاشتراك مع معلم؟',
            'يحتوي كل كورس على اختبارات قصيرة'      => 'تحتوي كل مجموعة على اختبارات قصيرة',
            'جميع الكورسات والملازم والشيتات'        => 'جميع الشروحات والملازم والشيتات',

            // Generic fallbacks for copy that has drifted from the seed.
            'الكورسات' => 'الحصص',
            'كورسات'   => 'حصص',
            'الكورس'   => 'الاشتراك',
            'كورس'     => 'اشتراك',
        ];

        $updated = 0;

        foreach (DB::table('platform_settings')->get(['id', 'value']) as $setting) {
            $original = (string) $setting->value;

            if (! str_contains($original, 'كورس')) {
                continue;
            }

            $value = strtr($original, $replacements);

            if ($value !== $original) {
                DB::table('platform_settings')->where('id', $setting->id)->update(['value' => $value]);
                $updated++;
            }
        }

        // The settings blob is cached forever and shared onto every page.
        Cache::forget('platform_settings');
    }

    public function down(): void
    {
        // The old wording described a feature that no longer exists.
    }
};
