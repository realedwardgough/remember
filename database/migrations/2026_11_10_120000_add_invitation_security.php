<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('registration_invites', function (Blueprint $table): void {
            $table->string('email')->nullable();
            $table->timestamp('expires_at')->nullable();
        });
        // Existing unused invitations receive a grace period after deployment.
        DB::table('registration_invites')->whereNull('accepted_at')->update(['expires_at' => now()->addHours(168)]);
    }

    public function down(): void
    {
        Schema::table('registration_invites', function (Blueprint $table): void {
            $table->dropColumn(['email', 'expires_at']);
        });
    }
};
