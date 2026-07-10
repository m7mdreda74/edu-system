<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_settings')->insertOrIgnore([
            ['key' => 'welcome_popup_active',       'value' => 'false',                               'type' => 'boolean', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'welcome_popup_title',        'value' => 'أهلاً بك في منصة التفوق التعليمية',   'type' => 'string',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'welcome_popup_item1_label',  'value' => 'طريقة إنشاء حساب جديد',               'type' => 'string',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'welcome_popup_item1_url',    'value' => 'https://docs.example.com/register',   'type' => 'string',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'welcome_popup_item2_label',  'value' => 'خطوات الدفع الإلكتروني',               'type' => 'string',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'welcome_popup_item2_url',    'value' => 'https://docs.example.com/payment',    'type' => 'string',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'welcome_popup_item3_label',  'value' => 'طريقة إيجاد رقم ID',                  'type' => 'string',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'welcome_popup_item3_url',    'value' => 'https://docs.example.com/id',         'type' => 'string',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'welcome_popup_item4_label',  'value' => 'طريقة تنزيل تطبيق المنصة على ويندوز', 'type' => 'string',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'welcome_popup_item4_url',    'value' => 'https://docs.example.com/windows',    'type' => 'string',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'welcome_popup_item5_label',  'value' => 'طريقة تنزيل تطبيق المنصة على آيباد وهاتف', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'welcome_popup_item5_url',    'value' => 'https://docs.example.com/mobile',     'type' => 'string',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'welcome_popup_item6_label',  'value' => 'كيفية حل مشكلة تسجيل الشاشة',         'type' => 'string',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'welcome_popup_item6_url',    'value' => 'https://docs.example.com/screen-record', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'welcome_popup_bottom_label', 'value' => 'للمزيد الإطلاع على دليل المستخدم',    'type' => 'string',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'welcome_popup_bottom_url',   'value' => 'https://docs.example.com/user-guide', 'type' => 'string',  'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('platform_settings')->where('key', 'like', 'welcome_popup_%')->delete();
    }
};
