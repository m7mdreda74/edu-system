<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Settings\Models\PlatformSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Platform settings the admin panel edits.
 *
 * These are updated rather than replaced — an admin may already have tuned the
 * page copy, and re-seeding demo data should not undo their work. Only the
 * operational keys the demo depends on are forced.
 */
class PlatformSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Forced: the demo will not behave correctly without these.
        $operational = [
            'commission_percent' => '20',
            'site_theme' => 'royal',
            'manual_payment_methods' => json_encode([
                [
                    'type' => 'bank',
                    'name' => 'بنك قطر الوطني (QNB)',
                    'account_number' => 'QA58 QNBA 0000 0000 0000 1234 5678',
                ],
                [
                    'type' => 'bank',
                    'name' => 'مصرف قطر الإسلامي (QIB)',
                    'account_number' => 'QA31 QISB 0000 0000 0000 8765 4321',
                ],
            ], JSON_UNESCAPED_UNICODE),
        ];

        foreach ($operational as $key => $value) {
            PlatformSetting::updateOrCreate(['key' => $key], ['value' => $value, 'type' => 'string']);
        }

        // Defaults: only filled in when the admin has not set them.
        $defaults = [
            'platform_name' => 'منصة التفوق',
            'contact_email' => 'support@altafawwuq.com',
            'whatsapp_url' => 'https://wa.me/97455556666',
            'footer_desc' => 'منصة تعليمية قطرية تربط الطالب بأفضل المعلمين — اختر صفك، شاهد طريقة الشرح، واحجز مع من يناسبك.',
            'home_hero_badge' => 'منصة التعليم الأولى في قطر',
            'home_hero_title' => 'تفوّق في دراستك',
            'home_hero_subtitle' => 'مع المعلم اللي يناسبك',
            'home_hero_desc' => 'اختر صفك، ثم المادة، ثم شاهد المعلمين وطريقة شرح كل واحد فيهم — واحجز مع اللي يناسبك.',
            'home_hero_btn1' => 'اختر صفك الآن',
            'home_hero_btn2' => 'إنشاء حساب مجاني',
        ];

        foreach ($defaults as $key => $value) {
            PlatformSetting::firstOrCreate(['key' => $key], ['value' => $value, 'type' => 'string']);
        }

        Cache::forget('platform_settings');
        Cache::forget('home.grades');
        Cache::forget('home.featured_teachers');
    }
}
