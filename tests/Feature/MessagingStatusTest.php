<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Messaging\StartConversation;
use App\Models\MessageAttachment;
use App\Models\MessagePushDelivery;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Messaging\PushTransport;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Minishlink\WebPush\VAPID;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessagingStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $keys = VAPID::createVapidKeys();
        config([
            'app.url' => 'https://remember.test', 'queue.default' => 'database',
            'remember.push.enabled' => true, 'remember.push.subject' => 'mailto:operator@example.test',
            'remember.push.public_key' => $keys['publicKey'], 'remember.push.private_key' => $keys['privateKey'],
            'remember.message_images.retention_hours' => 48, 'broadcasting.default' => 'null',
            'filesystems.disks.message_gcs.bucket' => 'status-private-message-bucket',
            'filesystems.disks.gcs.bucket' => 'status-private-timeline-bucket',
        ]);
        User::factory()->create();
    }

    private function report(int $exitCode = 0): array
    {
        $this->assertSame($exitCode, Artisan::call('messages:status', ['--json' => true]));
        return json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function configuration_checks_are_read_only_do_not_probe_services_and_do_not_claim_runtime_readiness(): void
    {
        Storage::shouldReceive('disk')->never();
        $transport = Mockery::mock(PushTransport::class);
        $transport->shouldNotReceive('send');
        $this->instance(PushTransport::class, $transport);
        $report = $this->report();
        $this->assertTrue($report['configuration_ok']);
        $checks = collect($report['checks'])->keyBy('name');
        $this->assertSame('ok', $checks['push']['status']);
        $this->assertSame('ok', $checks['image_storage']['status']);
        $this->assertSame('unverified', $checks['runtime']['status']);
        $this->assertSame('warning', $checks['realtime']['status']);
        $this->assertStringNotContainsString(config('remember.push.private_key'), Artisan::output());
        $this->assertStringNotContainsString('status-private-message-bucket', Artisan::output());
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('push_subscriptions', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->artisan('messages:status')->assertSuccessful();
    }

    #[Test]
    public function unsafe_configuration_returns_a_failure_without_printing_secrets(): void
    {
        config([
            'remember.push.private_key' => 'PRIVATE-VAPID-SECRET',
            'queue.connections.database.retry_after' => 15,
            'remember.message_images.retention_hours' => 0,
            'filesystems.disks.message_gcs.bucket' => config('filesystems.disks.gcs.bucket'),
            'broadcasting.default' => 'reverb', 'remember.realtime.enabled' => true,
            'broadcasting.connections.reverb.secret' => 'PRIVATE-REVERB-SECRET',
            'remember.realtime.scheme' => 'http', 'reverb.apps.apps.0.allowed_origins' => ['*'],
        ]);
        $checks = collect($this->report(1)['checks'])->keyBy('name');
        foreach (['push', 'queue_timeout', 'image_storage', 'image_retention', 'realtime_tls', 'realtime_origins'] as $check) {
            $this->assertSame('error', $checks[$check]['status']);
        }
        $this->assertStringNotContainsString('PRIVATE-', Artisan::output());
    }

    #[Test]
    public function operational_backlogs_are_counted_without_mutating_rows_or_showing_private_metadata(): void
    {
        $owner = User::factory()->create(['name' => 'PRIVATE-USER-NAME']);
        $conversation = app(StartConversation::class)->handle($owner, User::factory()->create()->id);
        $subscription = PushSubscription::query()->create([
            'user_id' => $owner->id, 'device_hash' => hash('sha256', 'device'), 'endpoint_hash' => hash('sha256', 'endpoint'),
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/PRIVATE-ENDPOINT', 'public_key' => 'PRIVATE-KEY', 'auth_token' => 'PRIVATE-AUTH',
        ]);
        foreach (['pending', 'failed'] as $state) {
            MessagePushDelivery::query()->create([
                'push_subscription_id' => $subscription->id, 'state' => $state,
                'next_attempt_at' => now()->subMinutes(2), 'expires_at' => now()->addMinutes(10),
            ]);
        }
        $image = MessageAttachment::query()->create([
            'conversation_id' => $conversation->id, 'uploaded_by_participant_id' => $conversation->participantFor($owner)->id,
            'client_request_id' => (string) Str::uuid(), 'source_hash' => str_repeat('a', 64),
            'disk' => 'message_gcs', 'path' => 'PRIVATE-PATH.webp', 'thumbnail_path' => 'PRIVATE-THUMB.webp',
            'width' => 10, 'height' => 10, 'size' => 10, 'retention_hours' => 48,
            'uploaded_at' => now()->subHours(49), 'expires_at' => now()->subMinutes(10), 'ready_at' => now()->subHours(49),
        ]);
        $jobs = DB::table('jobs')->count();
        $report = $this->report(1);
        $this->assertSame([
            'pending_push' => 1, 'overdue_push' => 1, 'failed_push' => 1,
            'expired_images_pending_removal' => 1, 'image_removal_overdue_five_minutes' => 1,
        ], $report['counts']);
        $this->assertStringNotContainsString('PRIVATE-', Artisan::output());
        $this->assertNull($image->fresh()->files_removed_at);
        $this->assertDatabaseHas('message_push_deliveries', ['state' => 'pending', 'attempts' => 0]);
        $this->assertSame($jobs, DB::table('jobs')->count());
    }

    #[Test]
    public function database_failures_are_sanitized_and_missing_migrations_are_reported(): void
    {
        Schema::shouldReceive('hasTable')->once()->andThrow(new \RuntimeException('PRIVATE-DSN credentials'));
        $checks = collect($this->report(1)['checks'])->keyBy('name');
        $this->assertSame('error', $checks['database']['status']);
        $this->assertStringNotContainsString('PRIVATE-DSN', Artisan::output());
        Schema::shouldReceive('hasTable')->andReturn(false);
        $checks = collect($this->report(1)['checks'])->keyBy('name');
        $this->assertStringContainsString('migrations', $checks['database']['message']);
    }

    #[Test]
    public function external_queue_visibility_is_left_unverified_and_synchronous_queues_fail(): void
    {
        config(['queue.default' => 'sqs', 'filesystems.disks.message_gcs.bucket' => '']);
        $checks = collect($this->report()['checks'])->keyBy('name');
        $this->assertSame('warning', $checks['queue_timeout']['status']);
        $this->assertSame('warning', $checks['image_storage']['status']);
        config(['queue.default' => 'sync']);
        $checks = collect($this->report(1)['checks'])->keyBy('name');
        $this->assertSame('error', $checks['queue']['status']);
        $this->assertSame('error', $checks['push']['status']);
    }
}
