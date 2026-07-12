<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Domain\Settings\Models\PlatformSetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Alter payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('status');
            $table->string('gateway_ref')->nullable()->change();
        });

        // 2. Seed manual payment methods setting key
        PlatformSetting::updateOrCreate(
            ['key' => 'manual_payment_methods'],
            [
                'value' => json_encode([
                    [
                        'name' => 'Vodafone Cash (محفظة كاش)',
                        'account_name' => 'أ. محمد أحمد',
                        'account_number' => '01001234567',
                        'instructions' => "1. قم بتحويل قيمة الكورس إلى رقم فودافون كاش الموضح أعلاه.\n2. التقط صورة للشاشة تفيد بنجاح عملية التحويل (إيصال الدفع).\n3. ارفع الصورة هنا في خانة الإيصال وسيتم تفعيل الكورس لك فور مراجعته."
                    ]
                ], JSON_UNESCAPED_UNICODE),
                'type' => 'string'
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('receipt_path');
        });

        PlatformSetting::where('key', 'manual_payment_methods')->delete();
    }
};
