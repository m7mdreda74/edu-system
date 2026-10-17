<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_resources', function (Blueprint $table): void {
            $table->text('admin_notes')->nullable()->after('rejected_at');
            $table->text('rejection_reason')->nullable()->after('admin_notes');
        });
    }

    public function down(): void
    {
        Schema::table('learning_resources', function (Blueprint $table): void {
            $table->dropColumn(['admin_notes', 'rejection_reason']);
        });
    }
};
