<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('message_post_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            // Keep only an identifier for safe send retries after a hard deletion; never snapshot content.
            $table->unsignedBigInteger('original_post_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_post_shares');
    }
};
