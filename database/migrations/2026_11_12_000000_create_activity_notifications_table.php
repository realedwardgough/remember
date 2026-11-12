<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'comment_id']);
            $table->index(['user_id', 'read_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_notifications');
    }
};
