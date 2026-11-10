<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('messaging_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('first_participant_id')->constrained('messaging_participants')->restrictOnDelete();
            $table->foreignId('second_participant_id')->constrained('messaging_participants')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['first_participant_id', 'second_participant_id'], 'conversations_pair_unique');
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_participant_id')->constrained('messaging_participants')->restrictOnDelete();
            $table->text('body');
            $table->uuid('client_request_id');
            $table->timestamps();
            $table->unique(['sender_participant_id', 'client_request_id'], 'messages_sender_request_unique');
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('conversation_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('messaging_participants')->restrictOnDelete();
            $table->foreignId('last_read_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamps();
            $table->unique(['conversation_id', 'participant_id'], 'conversation_memberships_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_memberships');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('messaging_participants');
    }
};
