<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('timelines', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier')->default('primary')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamp('setup_completed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timelines');
    }
};
