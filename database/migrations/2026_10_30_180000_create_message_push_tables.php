<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('message_alerts')->default(false);
            $table->timestamps();
        });
        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('device_hash', 64)->unique();
            $table->char('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->text('public_key');
            $table->text('auth_token');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('message_push_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('push_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('state', 16)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['message_id', 'push_subscription_id']);
            $table->index(['state', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_push_deliveries');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('notification_preferences');
    }
};
