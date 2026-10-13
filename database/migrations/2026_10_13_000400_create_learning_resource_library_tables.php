<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_teacher_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 80)->default('عام')->index();
            $table->json('tags')->nullable();
            $table->text('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('visibility', 20)->default('private')->index();
            $table->string('approval_status', 20)->default('none')->index();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_teacher_id', 'is_active', 'created_at']);
            $table->index(['visibility', 'approval_status', 'is_active']);
        });

        Schema::create('learning_resource_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learning_resource_id')->constrained('learning_resources')->cascadeOnDelete();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->cascadeOnDelete();
            $table->foreignId('attached_by_teacher_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['learning_resource_id', 'teaching_assignment_id'],
                'learning_resource_assignment_unique',
            );
            $table->index(
                ['teaching_assignment_id', 'learning_resource_id'],
                'learning_resource_assignment_lookup',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_resource_assignments');
        Schema::dropIfExists('learning_resources');
    }
};
