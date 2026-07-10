<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $aboutValues = [
            [
                'title' => 'رؤيتنا',
                'desc' => 'تمكين جميع الطلاب في دولة قطر من تحقيق الدرجات الكاملة في اختبارات الشهادة الثانوية من خلال تبسيط المفاهيم المعقدة وتوفير بيئة تعليمية مرنة ومتطورة.',
                'icon' => 'student'
            ],
            [
                'title' => 'رسالتنا',
                'desc' => 'تقديم تعليم تفاعلي متميز يعتمد على الجودة العالية، الصوت والصورة النقية، الحصص المباشرة والمسجلة، والمتابعة الأكاديمية المستمرة مع الطلاب وأولياء أمورهم.',
                'icon' => 'courses'
            ]
        ];

        $aboutPillars = [
            [
                'title' => 'التعليم المرن والمستمر',
                'desc' => 'nمنح الطالب كامل الحرية في الوصول للدروس المسجلة والحصص المباشرة في أي وقت ومكان، مع ملازم تفاعلية شاملة مطابقة لخطة الوزارة.'
            ],
            [
                'title' => 'تبسيط وتفكيك المعلومة',
                'desc' => 'نعتمد على استراتيجيات شرح حديثة تركز على الفهم والتطبيق العملي بدلاً من الحفظ التلقائي، لتبسيط المسائل والتمارين المعقدة.'
            ],
            [
                'title' => 'جودة الصوت والصورة الاحترافية',
                'desc' => 'نلتزم بتسجيل وعرض الفيديوهات بدقة HD وصوت نقي تماماً لضمان عدم تشتيت انتباه الطالب وتركيزه الكامل أثناء المذاكرة.'
            ],
            [
                'title' => 'تكامل الحصص التفاعلية والزووم',
                'desc' => 'نربط الدروس المسجلة بحصص تفاعلية مباشرة عبر Zoom لحل الواجبات، والإجابة عن استفسارات الطلاب بشكل شخصي وفعال.'
            ]
        ];

        DB::table('platform_settings')->insertOrIgnore([
            ['key' => 'about_title',    'value' => 'منصة التفوق التعليمية', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_badge',    'value' => 'منصتكم التعليمية الأولى', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_desc',     'value' => 'نصنع مستقبل التعليم في قطر من خلال تقديم أفضل الشروحات وأقوى المناهج التعليمية المتكاملة لطلاب المرحلة الثانوية على أيدي نخبة من أكفأ المعلمين.', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_values',   'value' => json_encode($aboutValues, JSON_UNESCAPED_UNICODE), 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'about_pillars',  'value' => json_encode($aboutPillars, JSON_UNESCAPED_UNICODE), 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            
            ['key' => 'app_title',      'value' => 'حمّل تطبيقات المنصة', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'app_badge',      'value' => 'تطبيقات التفوق للأجهزة الذكية', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'app_desc',       'value' => 'لضمان تجربة تعليمية سلسة وخالية من الانقطاع وبث فيديوهات فائق السرعة، حمّل تطبيقات منصة التفوق المخصصة لأجهزة الكمبيوتر والهواتف الذكية.', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'app_win_url',    'value' => '#', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'app_mac_url',    'value' => '#', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'app_ios_url',    'value' => '#', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'app_android_url','value' => '#', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'app_huawei_url', 'value' => '#', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            
            ['key' => 'contact_title',  'value' => 'تواصل معنا', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contact_badge',  'value' => 'الدعم الفني والاتصال', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'contact_phone',  'value' => '+974 4444 8888', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('platform_settings')->whereIn('key', [
            'about_title', 'about_badge', 'about_desc', 'about_values', 'about_pillars',
            'app_title', 'app_badge', 'app_desc', 'app_win_url', 'app_mac_url', 'app_ios_url', 'app_android_url', 'app_huawei_url',
            'contact_title', 'contact_badge', 'contact_phone'
        ])->delete();
    }
};
