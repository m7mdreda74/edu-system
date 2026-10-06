<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lesson_videos')) {
            Schema::create('lesson_videos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('group_material_id')->constrained('group_materials')->cascadeOnDelete();
                $table->string('provider', 50);
                $table->string('idempotency_key', 100);
                $table->string('provider_upload_id', 255)->nullable();
                $table->string('provider_asset_id', 255)->nullable();
                $table->string('status', 30)->index();
                $table->unsignedInteger('generation');
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->text('thumbnail_reference')->nullable();
                $table->text('playback_reference')->nullable();
                $table->string('failure_code', 100)->nullable();
                $table->text('failure_message')->nullable();
                $table->timestamp('provider_updated_at')->nullable();
                $table->timestamp('ready_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->unique(['group_material_id', 'idempotency_key'], 'lesson_videos_material_idempotency_unique');
                $table->unique(['provider', 'provider_upload_id'], 'lesson_videos_provider_upload_unique');
                $table->unique(['provider', 'provider_asset_id'], 'lesson_videos_provider_asset_unique');
                $table->unique(['group_material_id', 'generation'], 'lesson_videos_material_generation_unique');
                $table->index(['group_material_id', 'status']);
            });
        }

        if (! Schema::hasColumn('group_materials', 'active_lesson_video_id')) {
            Schema::table('group_materials', function (Blueprint $table): void {
                $table->foreignId('active_lesson_video_id')
                    ->nullable()
                    ->after('video_path')
                    ->constrained('lesson_videos')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('lesson_video_webhook_events')) {
            Schema::create('lesson_video_webhook_events', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 50);
                $table->string('event_key', 64);
                $table->foreignId('lesson_video_id')->nullable()->constrained('lesson_videos')->nullOnDelete();
                $table->string('event_type', 80)->nullable();
                $table->string('provider_asset_id', 255)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'event_key'], 'lesson_video_webhook_events_provider_key_unique');
                $table->index(['provider', 'provider_asset_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('group_materials', 'active_lesson_video_id')) {
            Schema::table('group_materials', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('active_lesson_video_id');
            });
        }

        Schema::dropIfExists('lesson_video_webhook_events');
        Schema::dropIfExists('lesson_videos');
    }
};
