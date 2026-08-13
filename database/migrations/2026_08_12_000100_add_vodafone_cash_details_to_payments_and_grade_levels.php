<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_levels', function (Blueprint $table): void {
            $table->string('vodafone_cash_number', 20)->nullable()->after('is_active');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('sender_phone', 20)->nullable()->after('gateway_ref');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('sender_phone');
        });

        Schema::table('grade_levels', function (Blueprint $table): void {
            $table->dropColumn('vodafone_cash_number');
        });
    }
};
