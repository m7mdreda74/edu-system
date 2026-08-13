<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Academic\Models\GradeLevel;
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
        $this->seedVodafoneCashNumbers();

        // Forced: the demo will not behave correctly without these.
        $operational = [
            'commission_percent' => '20',
            'site_theme' => 'royal',
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

    /**
     * Checkout now receives its Vodafone Cash number from the student's grade,
     * not from a global setting. Curriculum rows are owned by migrations, so
     * only fill a missing number and never replace one configured by an admin.
     */
    private function seedVodafoneCashNumbers(): void
    {
        GradeLevel::query()
            ->orderBy('id')
            ->get()
            ->each(function (GradeLevel $grade, int $index): void {
                if (filled($grade->vodafone_cash_number)) {
                    return;
                }

                // A deterministic Egyptian mobile-format demo number per grade.
                $grade->update([
                    'vodafone_cash_number' => sprintf('0109%07d', $index + 1),
                ]);
            });
    }
}
