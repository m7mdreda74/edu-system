<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $welcomeUrlKeys = [
            'welcome_popup_item1_url',
            'welcome_popup_item2_url',
            'welcome_popup_item3_url',
            'welcome_popup_item4_url',
            'welcome_popup_item5_url',
            'welcome_popup_item6_url',
            'welcome_popup_bottom_url',
        ];

        DB::table('platform_settings')
            ->whereIn('key', $welcomeUrlKeys)
            ->where('value', 'like', '%docs.example.com%')
            ->update(['value' => '', 'updated_at' => now()]);

        DB::table('platform_settings')
            ->whereIn('key', ['app_win_url', 'app_mac_url', 'app_ios_url', 'app_android_url', 'app_huawei_url'])
            ->whereIn('value', ['#', 'https://docs.example.com'])
            ->update(['value' => '', 'updated_at' => now()]);

        DB::table('platform_settings')
            ->where('key', 'home_youtube_videos')
            ->where('value', 'like', '%https://youtube.com%')
            ->update(['value' => json_encode([], JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);

        DB::table('platform_settings')
            ->where('key', 'footer_desc')
            ->where('value', 'منصة تعليمية متخصصة في مواد المرحلة الثانوية، نحو مستقبل أفضل لكل طالب.')
            ->update([
                'value' => 'منصة تعليمية قطرية تربط الطالب بأفضل المعلمين — اختر صفك، شاهد طريقة الشرح، واحجز مع من يناسبك.',
                'updated_at' => now(),
            ]);

        // These exact values were demo-only links, never teacher-provided media.
        DB::table('users')->where('intro_video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')->update(['intro_video_url' => null]);
        DB::table('group_materials')->where('video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')->update(['video_url' => null]);
        DB::table('live_sessions')->where('recording_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')->update(['recording_url' => null]);
    }

    public function down(): void
    {
        // Do not restore placeholder URLs or unrelated demo content.
    }
};
