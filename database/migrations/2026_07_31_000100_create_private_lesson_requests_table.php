<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_lesson_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->text('student_note')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();

            $table->index(['student_id', 'teaching_assignment_id', 'status'], 'private_requests_student_assignment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_lesson_requests');
    }
};
