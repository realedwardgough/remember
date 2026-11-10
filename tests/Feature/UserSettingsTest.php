<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Egough\LaravelSettings\ModelSettings\Models\ModelSetting;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    use DatabaseMigrations;

    #[Test]
    public function preferences_default_to_opt_out_are_typed_and_are_isolated_per_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->assertFalse($first->wantsMessageAlerts());
        $this->assertFalse($first->wantsPostAlerts());
        $first->settings()->set('notifications.message_alerts', true);
        $first->settings()->set('notifications.post_alerts', true);

        $this->assertTrue($first->wantsMessageAlerts());
        $this->assertTrue($first->wantsPostAlerts());
        $this->assertFalse($second->wantsMessageAlerts());
        $this->assertFalse($second->wantsPostAlerts());
        $this->assertSame([$first->id], User::query()->whereSettingEnabled('notifications.post_alerts')->pluck('id')->all());

        // A write invalidates an already-primed cache, including for another instance.
        $first->settings()->set('notifications.message_alerts', false);
        $this->assertFalse($first->fresh()->wantsMessageAlerts());
        $first->settings()->forget('notifications.post_alerts');
        $this->assertFalse($first->wantsPostAlerts());
    }

    #[Test]
    public function opt_in_queries_require_a_boolean_setting_on_the_user_model(): void
    {
        $user = User::factory()->create();
        $user->settings()->set('notifications.post_alerts', 'true');
        $this->assertFalse($user->wantsPostAlerts());
        $this->assertSame(0, User::query()->whereSettingEnabled('notifications.post_alerts')->count());

        $post = Post::factory()->create();
        // Another model's setting, even with the same key and owner ID, is unrelated.
        app(\Egough\LaravelSettings\ModelSettings\ModelSettingsManager::class)->for($post)
            ->set('notifications.post_alerts', true);
        $this->assertSame(0, User::query()->whereSettingEnabled('notifications.post_alerts')->count());
    }

    #[Test]
    public function deleting_a_user_removes_their_settings_and_invalidates_cached_values(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->settings()->set('notifications.message_alerts', true);
        $user->settings()->set('ui.theme', 'dark');
        $other->settings()->set('ui.theme', 'light');
        $this->assertTrue($user->wantsMessageAlerts());
        $user->delete();

        $this->assertSame([], $user->settings()->all());
        $this->assertFalse(ModelSetting::query()->where('settingable_type', $user->getMorphClass())->where('settingable_id', $user->id)->exists());
        $this->assertSame('light', $other->settings()->get('ui.theme'));
    }

    #[Test]
    public function migrating_existing_preferences_and_rolling_back_preserves_current_choices(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $first->settings()->set('ui.theme', 'dark');
        $migration = require database_path('migrations/2026_11_01_000001_migrate_notification_preferences_to_user_settings.php');

        $migration->down();
        DB::table('notification_preferences')->where('user_id', $first->id)->update(['message_alerts' => true, 'post_alerts' => false]);
        DB::table('notification_preferences')->where('user_id', $second->id)->update(['message_alerts' => false, 'post_alerts' => true]);
        $migration->up();

        $this->assertFalse(Schema::hasTable('notification_preferences'));
        $this->assertTrue($first->wantsMessageAlerts());
        $this->assertFalse($first->wantsPostAlerts());
        $this->assertFalse($second->wantsMessageAlerts());
        $this->assertTrue($second->wantsPostAlerts());
        $this->assertSame('dark', $first->settings()->get('ui.theme'));

        $first->settings()->set('notifications.message_alerts', false);
        $first->settings()->set('notifications.post_alerts', true);
        $migration->down();
        $this->assertDatabaseHas('notification_preferences', ['user_id' => $first->id, 'message_alerts' => false, 'post_alerts' => true]);
        $this->assertSame('dark', $first->settings()->get('ui.theme'));
        $migration->up();
        $this->assertFalse($first->wantsMessageAlerts());
        $this->assertTrue($first->wantsPostAlerts());
    }

    #[Test]
    public function migration_does_not_overwrite_preferences_already_stored_in_the_package(): void
    {
        $user = User::factory()->create();
        $migration = require database_path('migrations/2026_11_01_000001_migrate_notification_preferences_to_user_settings.php');
        $migration->down();
        DB::table('notification_preferences')->where('user_id', $user->id)->update(['message_alerts' => true]);
        $user->settings()->set('notifications.message_alerts', false);
        $migration->up();

        $this->assertFalse($user->wantsMessageAlerts());
    }
}
