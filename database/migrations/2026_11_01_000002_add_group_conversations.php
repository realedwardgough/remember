<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->unsignedBigInteger('first_participant_id')->nullable()->change();
            $table->unsignedBigInteger('second_participant_id')->nullable()->change();
            $table->boolean('is_group')->default(false);
            $table->string('name', 80)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('creation_request_id')->nullable();
            $table->unique(['created_by_user_id', 'creation_request_id'], 'conversations_creation_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('conversations')->where('is_group', true)->exists()) {
            throw new RuntimeException('Group conversations exist. Export or remove them before reverting group messaging.');
        }

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropUnique('conversations_creation_unique');
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn(['is_group', 'name', 'creation_request_id']);
            $table->unsignedBigInteger('first_participant_id')->nullable(false)->change();
            $table->unsignedBigInteger('second_participant_id')->nullable(false)->change();
        });
    }
};
