<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teacher Payouts and Platform Commission tables.
 * Financial data — NEVER cascade delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Teacher Payouts ──────────────────────────────────────────
        if (! Schema::hasTable('teacher_payouts')) {
            Schema::create('teacher_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->integer('amount');                  // in halala — no floats
            $table->integer('platform_commission');     // amount withheld by platform
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('pending'); // pending | paid
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'status']);
        });
        }

        // ─── Platform Settings ────────────────────────────────────────
        if (! Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type')->default('string');
                $table->timestamps();
            });

            \Illuminate\Support\Facades\DB::table('platform_settings')->insert([
                ['key' => 'commission_percent',  'value' => '20',   'type' => 'integer', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'platform_name',       'value' => 'التفوق', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'registration_open',   'value' => 'true', 'type' => 'boolean', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('teacher_payouts');
    }
};
