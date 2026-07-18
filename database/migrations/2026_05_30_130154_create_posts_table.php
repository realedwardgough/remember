<?php

declare(strict_types=1);

use App\Enum\TimelinePostType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->enum('post_type', array_map(
                static fn (TimelinePostType $type): string => $type->value,
                TimelinePostType::cases(),
            ))->default(TimelinePostType::MEMORY->value);
            $table->date('published_at');
            $table->string('visibility')->default('family');
            $table->timestamps();

            $table->index(['published_at', 'id']);
            $table->index(['post_type', 'published_at']);
            $table->index(['author_id', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
