<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('activity_notifications', function (Blueprint $table): void {
            $table->unsignedBigInteger('comment_id')->nullable()->change();
            $table->foreignId('heart_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'heart_id']);
        });
        Schema::table('message_push_deliveries', function (Blueprint $table): void {
            $table->foreignId('activity_notification_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['activity_notification_id', 'push_subscription_id'], 'activity_device_unique');
        });
    }

    public function down(): void
    {
        Schema::table('message_push_deliveries', function (Blueprint $table): void {
            $table->dropUnique('activity_device_unique');
            $table->dropConstrainedForeignId('activity_notification_id');
        });
        // Like notifications have no comment and cannot exist in the old schema.
        \Illuminate\Support\Facades\DB::table('activity_notifications')->whereNull('comment_id')->delete();
        Schema::table('activity_notifications', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'heart_id']);
            $table->dropConstrainedForeignId('heart_id');
            $table->unsignedBigInteger('comment_id')->nullable(false)->change();
        });
    }
};
