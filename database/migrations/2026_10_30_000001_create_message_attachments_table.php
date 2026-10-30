<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by_participant_id')->constrained('messaging_participants')->restrictOnDelete();
            $table->uuid('client_request_id');
            $table->char('source_hash', 64);
            $table->string('disk')->default('message_gcs');
            $table->string('path');
            $table->string('thumbnail_path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('retention_hours');
            $table->timestamp('uploaded_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('files_removed_at')->nullable();
            $table->timestamps();
            $table->unique(['uploaded_by_participant_id', 'client_request_id'], 'message_attachments_request_unique');
            $table->index(['files_removed_at', 'message_id', 'uploaded_at'], 'message_attachments_cleanup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};
