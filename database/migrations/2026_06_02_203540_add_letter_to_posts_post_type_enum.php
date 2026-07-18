<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE posts MODIFY post_type ENUM('Event', 'Milestone', 'Memory', 'Letter') NOT NULL DEFAULT 'Memory'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('posts')
            ->where('post_type', 'Letter')
            ->update(['post_type' => 'Memory']);

        DB::statement("ALTER TABLE posts MODIFY post_type ENUM('Event', 'Milestone', 'Memory') NOT NULL DEFAULT 'Memory'");
    }
};
