<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Messaging\SendMessage;
use App\Actions\Messaging\StartConversation;
use App\Http\Middleware\BindPushDevice;
use App\Jobs\SendMessagePush;
use App\Models\ConversationMembership;
use App\Models\MessagePushDelivery;
use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Messaging\PushEndpoint;
use App\Services\Messaging\PushSettings;
use App\Services\Messaging\PushTransport;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Minishlink\WebPush\VAPID;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageNotificationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private array $keys;
    private string $device = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        $this->keys = VAPID::createVapidKeys();
        config(['queue.default' => 'database', 'remember.push.enabled' => true,
            'remember.push.public_key' => $this->keys['publicKey'], 'remember.push.private_key' => $this->keys['privateKey'],
            'remember.push.subject' => 'mailto:notifications@example.com']);
        Queue::fake();
    }

    private function subscribe(User $user, ?string $device = null, string $suffix = 'device'): PushSubscription
    {
        return PushSubscription::query()->create([
            'user_id' => $user->id, 'device_hash' => hash('sha256', $device ?? $this->device),
            'endpoint_hash' => hash('sha256', 'https://fcm.googleapis.com/fcm/send/'.$suffix),
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.$suffix,
            'public_key' => $this->keys['publicKey'], 'auth_token' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
        ]);
    }

    private function pending(): array
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        NotificationPreference::query()->create(['user_id' => $recipient->id, 'message_alerts' => true]);
        $device = $this->subscribe($recipient);
        $conversation = app(StartConversation::class)->handle($sender, $recipient->id);
        $message = app(SendMessage::class)->handle($sender, $conversation, 'Secret message 🥰', (string) Str::uuid());
        return [$sender, $recipient, $device, $message, MessagePushDelivery::query()->firstOrFail()];
    }

    private function deliver(MessagePushDelivery $delivery, ?int $status = 201): void
    {
        $this->travel(6)->seconds();
        $transport = Mockery::mock(PushTransport::class);
        $transport->shouldReceive('send')->once()->andReturn($status);
        (new SendMessagePush($delivery->id))->handle($transport, app(PushSettings::class));
    }

    #[Test]
    public function preferences_are_private_opt_in_and_distinct_from_this_device(): void
    {
        $user = User::factory()->create();
        $this->getJson(route('notifications.show'))->assertUnauthorized();
        $this->actingAs($user)->withCookie(BindPushDevice::COOKIE, $this->device)
            ->getJson(route('notifications.show'))->assertOk()->assertJsonPath('message_alerts', false)->assertJsonPath('device_enabled', false)
            ->assertJsonMissingPath('private_key')->assertHeader('Cache-Control', 'no-store, private');
        $this->putJson(route('notifications.update'), ['message_alerts' => true])->assertOk()->assertJsonPath('message_alerts', true)->assertJsonPath('device_enabled', false);
        $this->assertDatabaseCount('push_subscriptions', 0);
        $other = User::factory()->create();
        $this->actingAs($other)->getJson(route('notifications.show'))->assertJsonPath('message_alerts', false);
        $this->putJson(route('notifications.update'), ['message_alerts' => 'invalid'])->assertUnprocessable();
    }

    #[Test]
    public function registration_validates_keys_and_endpoints_and_encrypts_secrets(): void
    {
        $user = User::factory()->create();
        $body = ['endpoint' => 'https://web.push.apple.com/QSubscription/example', 'keys' => [
            'p256dh' => $this->keys['publicKey'], 'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
        ]];
        $this->actingAs($user)->withCookie(BindPushDevice::COOKIE, $this->device)
            ->postJson(route('notifications.subscribe'), $body)->assertOk()->assertJsonPath('device_enabled', true)->assertJsonPath('message_alerts', false)
            ->assertDontSee($body['endpoint'])->assertDontSee($body['keys']['auth']);
        $this->postJson(route('notifications.subscribe'), $body)->assertOk();
        $this->assertDatabaseCount('push_subscriptions', 1);
        $stored = DB::table('push_subscriptions')->first();
        $this->assertNotSame($body['endpoint'], $stored->endpoint);
        $this->assertNotSame($body['keys']['auth'], $stored->auth_token);
        $this->assertArrayNotHasKey('endpoint', PushSubscription::query()->first()->toArray());
        $bad = $body;
        $bad['keys']['auth'] = 'too-short';
        $this->postJson(route('notifications.subscribe'), $bad)->assertUnprocessable();
        $bad = $body;
        $bad['endpoint'] = 'https://127.0.0.1/private';
        $this->postJson(route('notifications.subscribe'), $bad)->assertUnprocessable();
        $body['expirationTime'] = 1000;
        $this->postJson(route('notifications.subscribe'), $body)->assertUnprocessable();
    }

    #[Test]
    public function another_device_cannot_claim_an_existing_endpoint(): void
    {
        $owner = User::factory()->create();
        $device = $this->subscribe($owner);
        $this->actingAs(User::factory()->create())->withCookie(BindPushDevice::COOKIE, str_repeat('b', 64))
            ->postJson(route('notifications.subscribe'), ['endpoint' => $device->endpoint, 'keys' => ['p256dh' => $device->public_key, 'auth' => $device->auth_token]])->assertUnprocessable();
        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $owner->id]);
    }

    #[Test]
    public function logout_and_account_switch_revoke_only_the_current_device(): void
    {
        $user = User::factory()->create();
        $this->subscribe($user);
        $otherDevice = $this->subscribe($user, str_repeat('b', 64), 'other');
        $this->actingAs($user)->withCookie(BindPushDevice::COOKIE, $this->device)->post(route('logout'))->assertRedirect();
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', ['id' => $otherDevice->id]);
        $this->subscribe($user);
        $this->actingAs(User::factory()->create())->getJson(route('notifications.show'))->assertJsonPath('device_enabled', false);
        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    #[Test]
    public function disabling_a_device_keeps_account_preferences_and_other_devices(): void
    {
        $user = User::factory()->create();
        NotificationPreference::query()->create(['user_id' => $user->id, 'message_alerts' => true]);
        $this->subscribe($user);
        $this->subscribe($user, str_repeat('b', 64), 'second');
        $this->actingAs($user)->withCookie(BindPushDevice::COOKIE, $this->device)->deleteJson(route('notifications.unsubscribe'))
            ->assertOk()->assertJsonPath('device_enabled', false)->assertJsonPath('message_alerts', true);
        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    #[Test]
    public function queueing_waits_for_commit_and_rollbacks_leave_no_delivery(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        NotificationPreference::query()->create(['user_id' => $recipient->id, 'message_alerts' => true]);
        $this->subscribe($recipient);
        $conversation = app(StartConversation::class)->handle($sender, $recipient->id);
        DB::beginTransaction();
        $message = app(SendMessage::class)->handle($sender, $conversation, 'Committed', (string) Str::uuid());
        Queue::assertNotPushed(SendMessagePush::class);
        DB::commit();
        Queue::assertPushed(SendMessagePush::class, 1);
        app(SendMessage::class)->handle($sender, $conversation, $message->body, $message->client_request_id);
        Queue::assertPushed(SendMessagePush::class, 1);
        DB::beginTransaction();
        app(SendMessage::class)->handle($sender, $conversation, 'Rolled back', (string) Str::uuid());
        $this->assertDatabaseCount('message_push_deliveries', 2);
        DB::rollBack();
        $this->assertDatabaseCount('message_push_deliveries', 1);
        $this->assertDatabaseMissing('messages', ['body' => 'Rolled back']);
        Queue::assertPushed(SendMessagePush::class, 1);
    }

    #[Test]
    public function only_recipient_devices_receive_message_previews_and_success_is_deduplicated(): void
    {
        [$sender, $recipient, $device, $message, $delivery] = $this->pending();
        $this->subscribe($sender, str_repeat('c', 64), 'sender');
        $this->assertDatabaseCount('message_push_deliveries', 1);
        $this->travel(6)->seconds();
        $transport = Mockery::mock(PushTransport::class);
        $transport->shouldReceive('send')->once()->withArgs(function ($subscription, $payload) use ($device, $message, $sender): bool {
            $this->assertSame($device->id, $subscription->id);
            $this->assertSame('Secret message 🥰', $payload['body']);
            $this->assertSame($sender->name.' · Remember', $payload['title']);
            $this->assertSame('/messages/'.$message->conversation_id, $payload['url']);
            $this->assertArrayNotHasKey('endpoint', $payload);
            return true;
        })->andReturn(201);
        $job = new SendMessagePush($delivery->id);
        $job->handle($transport, app(PushSettings::class));
        $job->handle($transport, app(PushSettings::class));
        $this->assertSame('sent', $delivery->fresh()->state);
        $this->assertSame(1, $delivery->fresh()->attempts);
    }

    #[Test]
    public function read_before_delivery_and_paused_preferences_skip_alerts(): void
    {
        [$sender, $recipient, $device, $message, $delivery] = $this->pending();
        ConversationMembership::query()->where('conversation_id', $message->conversation_id)
            ->where('participant_id', $message->conversation->participantFor($recipient)->id)->update(['last_read_message_id' => $message->id]);
        $this->travel(6)->seconds();
        $transport = Mockery::mock(PushTransport::class);
        $transport->shouldNotReceive('send');
        (new SendMessagePush($delivery->id))->handle($transport, app(PushSettings::class));
        $this->assertSame('skipped', $delivery->fresh()->state);
        $next = app(SendMessage::class)->handle($sender, $message->conversation, 'Next', (string) Str::uuid());
        $pending = MessagePushDelivery::query()->where('message_id', $next->id)->firstOrFail();
        NotificationPreference::query()->where('user_id', $recipient->id)->update(['message_alerts' => false]);
        $this->travel(6)->seconds();
        (new SendMessagePush($pending->id))->handle($transport, app(PushSettings::class));
        $this->assertSame('skipped', $pending->fresh()->state);
    }

    #[Test]
    public function opt_out_creates_no_delivery_and_every_opted_in_device_is_targeted(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $this->subscribe($recipient);
        $this->subscribe($recipient, str_repeat('b', 64), 'other');
        $conversation = app(StartConversation::class)->handle($sender, $recipient->id);
        app(SendMessage::class)->handle($sender, $conversation, 'No opt in', (string) Str::uuid());
        $this->assertDatabaseCount('message_push_deliveries', 0);
        NotificationPreference::query()->create(['user_id' => $recipient->id, 'message_alerts' => true]);
        app(SendMessage::class)->handle($sender, $conversation, 'Opted in', (string) Str::uuid());
        $this->assertDatabaseCount('message_push_deliveries', 2);
    }

    #[Test]
    public function expired_endpoints_are_removed_and_temporary_failures_have_bounded_retries(): void
    {
        [, , $device, , $delivery] = $this->pending();
        $this->deliver($delivery, 410);
        $this->assertDatabaseMissing('push_subscriptions', ['id' => $device->id]);
        $this->assertDatabaseMissing('message_push_deliveries', ['id' => $delivery->id]);
        [, , , , $retry] = $this->pending();
        $transport = Mockery::mock(PushTransport::class);
        $transport->shouldReceive('send')->times(4)->andReturn(503);
        for ($i = 0; $i < 4; $i++) {
            $this->travelTo($retry->fresh()->next_attempt_at->addSecond());
            (new SendMessagePush($retry->id))->handle($transport, app(PushSettings::class));
        }
        $this->assertSame('failed', $retry->fresh()->state);
        $this->assertSame(4, $retry->fresh()->attempts);
    }

    #[Test]
    public function revoked_devices_deleted_users_and_stale_deliveries_cannot_send(): void
    {
        [, $recipient, $device, , $delivery] = $this->pending();
        $recipient->delete();
        $this->assertDatabaseCount('push_subscriptions', 0);
        $transport = Mockery::mock(PushTransport::class);
        $transport->shouldNotReceive('send');
        (new SendMessagePush($delivery->id))->handle($transport, app(PushSettings::class));
        [, , , , $stale] = $this->pending();
        $this->travel(16)->minutes();
        (new SendMessagePush($stale->id))->handle($transport, app(PushSettings::class));
        $this->assertSame('skipped', $stale->fresh()->state);
    }

    #[Test]
    public function explicit_test_is_device_scoped_and_allowed_when_account_alerts_are_off(): void
    {
        $user = User::factory()->create();
        $device = $this->subscribe($user);
        $this->actingAs($user)->withCookie(BindPushDevice::COOKIE, $this->device)->postJson(route('notifications.test'))->assertAccepted();
        $delivery = MessagePushDelivery::query()->firstOrFail();
        $this->assertNull($delivery->message_id);
        $this->assertSame($device->id, $delivery->push_subscription_id);
        $transport = Mockery::mock(PushTransport::class);
        $transport->shouldReceive('send')->once()->withArgs(fn ($device, $payload) => $payload['body'] === 'Notifications are working on this device.' && $payload['url'] === '/profile#notifications')->andReturn(201);
        (new SendMessagePush($delivery->id))->handle($transport, app(PushSettings::class));
        $this->actingAs(User::factory()->create())->postJson(route('notifications.test'))->assertUnprocessable();
    }

    #[Test]
    public function endpoint_validation_blocks_ssrf_and_pins_public_dns_without_redirects(): void
    {
        $endpoints = new PushEndpoint;
        foreach (['http://fcm.googleapis.com/send/x', 'https://fcm.googleapis.com:8443/x', 'https://fcm.googleapis.com.evil.test/x',
            'https://127.0.0.1/x', 'https://user@fcm.googleapis.com/x', 'https://fcm.googleapis.com/x#fragment', 'https://evil.push.apple.com.evil.test/x',
            'https://169.254.169.254/latest/meta-data', 'https://fcm.googleapis.com\\@localhost/x'] as $url) {
            try {
                $endpoints->host($url);
                $this->fail('Accepted unsafe endpoint');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('web.push.apple.com', $endpoints->host('https://web.push.apple.com/QSubscription/token'));
        $safe = Mockery::mock(PushEndpoint::class)->makePartial();
        $safe->shouldReceive('resolve')->with('fcm.googleapis.com')->once()->andReturn(['8.8.8.8']);
        $options = $safe->clientOptions('https://fcm.googleapis.com/fcm/send/token');
        $this->assertFalse($options['allow_redirects']);
        $this->assertSame('', $options['proxy']);
        $this->assertTrue($options['verify']);
        $this->assertSame(['fcm.googleapis.com:443:8.8.8.8'], $options['curl'][CURLOPT_RESOLVE]);
        $this->assertInstanceOf(\GuzzleHttp\Handler\CurlHandler::class, $options['handler']);
    }

    #[Test]
    public function missing_configuration_and_sync_queue_leave_messaging_working(): void
    {
        config(['remember.push.private_key' => '']);
        $this->assertFalse(app(PushSettings::class)->available());
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = app(StartConversation::class)->handle($sender, $recipient->id);
        app(SendMessage::class)->handle($sender, $conversation, 'Still works', (string) Str::uuid());
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('message_push_deliveries', 0);
        config(['remember.push.private_key' => $this->keys['privateKey'], 'queue.default' => 'sync']);
        $this->assertFalse(app(PushSettings::class)->available());
        $this->actingAs($recipient)->postJson(route('notifications.subscribe'), [])->assertServiceUnavailable();
    }
    #[Test]
    public function real_transport_encrypts_the_payload_and_signs_vapid_without_external_requests(): void
    {
        $device = $this->subscribe(User::factory()->create());
        $history = [];
        $handler = \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler([new \GuzzleHttp\Psr7\Response(201)]));
        $handler->push(\GuzzleHttp\Middleware::history($history));
        $endpoints = Mockery::mock(PushEndpoint::class);
        $endpoints->shouldReceive('clientOptions')->once()->with($device->endpoint)->andReturn(['handler' => $handler]);
        $this->instance(PushEndpoint::class, $endpoints);
        $status = app(PushTransport::class)->send($device, ['body' => 'You have a new message in Remember.']);
        $this->assertSame(201, $status);
        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('aes128gcm', $request->getHeaderLine('Content-Encoding'));
        $this->assertStringStartsWith('vapid ', $request->getHeaderLine('Authorization'));
        $this->assertSame('60', $request->getHeaderLine('TTL'));
        $this->assertStringNotContainsString('new message', (string) $request->getBody());
    }

    #[Test]
    public function queue_outages_leave_a_saved_message_and_recoverable_outbox_without_secret_logs(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $this->subscribe($recipient);
        NotificationPreference::query()->create(['user_id' => $recipient->id, 'message_alerts' => true]);
        $conversation = app(StartConversation::class)->handle($sender, $recipient->id);
        Log::spy();
        $originalQueue = Queue::getFacadeRoot();
        Queue::shouldReceive('connection')->andThrow(new \RuntimeException('SECRET endpoint/auth token'));
        app(SendMessage::class)->handle($sender, $conversation, 'Saved during queue outage', (string) Str::uuid());
        $this->assertDatabaseHas('messages', ['body' => 'Saved during queue outage']);
        $this->assertDatabaseHas('message_push_deliveries', ['state' => 'pending', 'attempts' => 0]);
        Log::shouldHaveReceived('warning')->with('Message notification could not be queued.', ['delivery_id' => MessagePushDelivery::query()->firstOrFail()->id])->once();
        Queue::swap($originalQueue);
        Queue::fake();
        $this->travel(61)->seconds();
        $this->artisan('messages:retry-push')->assertSuccessful();
        Queue::assertPushed(SendMessagePush::class, 1);
    }

    #[Test]
    public function dns_answers_must_all_be_public_before_an_address_can_be_used(): void
    {
        $endpoints = new PushEndpoint;
        foreach ([[], [['ip' => '127.0.0.1']], [['ip' => '10.1.2.3']], [['ip' => '169.254.169.254']],
            [['ipv6' => '::1']], [['ipv6' => '::ffff:127.0.0.1']], [['ipv6' => 'fc00::1']],
            [['ip' => '8.8.8.8'], ['ip' => '192.168.1.2']]] as $records) {
            try {
                $endpoints->safeAddresses($records);
                $this->fail('Accepted unsafe DNS');
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(['8.8.8.8', '2606:4700:4700::1111'], $endpoints->safeAddresses([['ip' => '8.8.8.8'], ['ipv6' => '2606:4700:4700::1111']]));
    }

    #[Test]
    public function local_setup_generates_stable_private_keys_without_printing_or_replacing_them(): void
    {
        $directory = sys_get_temp_dir().'/remember-push-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $originalPath = app()->environmentPath();
        $originalFile = app()->environmentFile();
        $originalEnvironment = app('env');
        try {
            app()->useEnvironmentPath($directory)->loadEnvironmentFrom('.env');
            app()->instance('env', 'local');
            config(['app.url' => 'https://remember.test', 'remember.push.subject' => '']);
            file_put_contents($directory.'/.env', "APP_NAME=Remember\nUNRELATED_SETTING=keep-this\n");
            $this->artisan('messages:setup-local-push')->expectsOutput('Local Web Push configured. Keys were saved privately and were not printed. Start a database queue worker and the scheduler.')->assertSuccessful();
            $content = file_get_contents($directory.'/.env');
            $this->assertStringContainsString('MESSAGE_PUSH_ENABLED=true', $content);
            $this->assertStringContainsString('UNRELATED_SETTING=keep-this', $content);
            $this->assertStringContainsString('VAPID_PRIVATE_KEY=', $content);
            $this->assertSame(0600, fileperms($directory.'/.env') & 0777);
            $this->artisan('messages:setup-local-push')->assertFailed();
            $this->assertSame($content, file_get_contents($directory.'/.env'));
            app()->instance('env', 'production');
            $this->artisan('messages:setup-local-push')->assertFailed();
            $this->assertSame($content, file_get_contents($directory.'/.env'));
        } finally {
            app()->useEnvironmentPath($originalPath)->loadEnvironmentFrom($originalFile);
            app()->instance('env', $originalEnvironment);
            unlink($directory.'/.env');
            rmdir($directory);
        }
    }

}
