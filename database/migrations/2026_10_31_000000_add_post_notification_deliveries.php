<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->boolean('post_alerts')->default(false);
        });
        Schema::table('message_push_deliveries', function (Blueprint $table): void {
            $table->string('kind', 16)->default('message');
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->unique(['post_id', 'push_subscription_id']);
        });
        // Preserve explicit tests queued before this deployment. Deleted posts must never become tests.
        DB::table('message_push_deliveries')->whereNull('message_id')->update(['kind' => 'test']);
    }

    public function down(): void
    {
        Schema::table('message_push_deliveries', function (Blueprint $table): void {
            $table->dropUnique(['post_id', 'push_subscription_id']);
            $table->dropConstrainedForeignId('post_id');
            $table->dropColumn('kind');
        });
        Schema::table('notification_preferences', fn (Blueprint $table) => $table->dropColumn('post_alerts'));
    }
};
