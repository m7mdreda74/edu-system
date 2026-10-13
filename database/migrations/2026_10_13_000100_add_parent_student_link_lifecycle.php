<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_student_links', function (Blueprint $table): void {
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->index(['parent_user_id', 'status', 'expires_at'], 'idx_parent_links_lifecycle');
            $table->index(['student_user_id', 'status', 'expires_at'], 'idx_student_links_lifecycle');
        });

        DB::table('parent_student_links')
            ->whereNotNull('verified_at')
            ->update(['status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('parent_student_links', function (Blueprint $table): void {
            $table->dropForeign(['requested_by_user_id']);
            $table->dropIndex('idx_parent_links_lifecycle');
            $table->dropIndex('idx_student_links_lifecycle');
            $table->dropColumn([
                'status',
                'requested_by_user_id',
                'expires_at',
                'responded_at',
            ]);
        });
    }
};
