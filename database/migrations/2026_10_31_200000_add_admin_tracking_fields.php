<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'created_by')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('remember_token')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('created_by');
            });
        }

        if (Schema::hasTable('coupons') && ! Schema::hasColumn('coupons', 'created_by')) {
            Schema::table('coupons', function (Blueprint $table): void {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('usage_limit')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('created_by');
            });
        }

        if (Schema::hasTable('teaching_groups') && ! Schema::hasColumn('teaching_groups', 'created_by')) {
            Schema::table('teaching_groups', function (Blueprint $table): void {
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('created_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('teaching_groups') && Schema::hasColumn('teaching_groups', 'created_by')) {
            Schema::table('teaching_groups', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('created_by');
            });
        }

        if (Schema::hasTable('coupons') && Schema::hasColumn('coupons', 'created_by')) {
            Schema::table('coupons', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('created_by');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'created_by')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('created_by');
            });
        }
    }
};
