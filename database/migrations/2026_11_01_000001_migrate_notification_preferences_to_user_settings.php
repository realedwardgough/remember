<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::table('notification_preferences')->orderBy('id')->chunkById(100, function ($preferences): void {
            $users = User::query()->whereIn('id', $preferences->pluck('user_id'))->get()->keyBy('id');

            foreach ($preferences as $preference) {
                $user = $users->get($preference->user_id);

                if ($user === null) {
                    continue;
                }

                $settings = $user->settings();
                $existing = $settings->all();

                foreach (['message_alerts', 'post_alerts'] as $key) {
                    if (! array_key_exists('notifications.'.$key, $existing)) {
                        $settings->set('notifications.'.$key, (bool) $preference->{$key});
                    }
                }
            }
        });

        Schema::drop('notification_preferences');
    }

    public function down(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('message_alerts')->default(false);
            $table->boolean('post_alerts')->default(false);
            $table->timestamps();
        });

        User::query()->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                DB::table('notification_preferences')->insert([
                    'user_id' => $user->id,
                    'message_alerts' => $user->wantsMessageAlerts(),
                    'post_alerts' => $user->wantsPostAlerts(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $user->settings()->forget('notifications.message_alerts');
                $user->settings()->forget('notifications.post_alerts');
            }
        });
    }
};
