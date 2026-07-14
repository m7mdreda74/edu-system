<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index(); // pending | approved | rejected
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_user_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('purchase_request_id')
                ->nullable()
                ->constrained('purchase_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['purchase_request_id']);
            $table->dropColumn('purchase_request_id');
        });

        Schema::dropIfExists('purchase_requests');
    }
};
