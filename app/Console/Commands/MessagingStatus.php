<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MessageAttachment;
use App\Models\MessagePushDelivery;
use App\Services\Messaging\MessageImageSettings;
use App\Services\Messaging\PushSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MessagingStatus extends Command
{
    protected $signature = 'messages:status {--json : Return a machine-readable report without secrets}';
    protected $description = 'Read messaging configuration and backlog counts without sending alerts or contacting storage';

    public function handle(MessageImageSettings $images, PushSettings $push): int
    {
        $checks = [];
        $add = function (string $name, string $status, string $message) use (&$checks): void {
            $checks[] = compact('name', 'status', 'message');
        };
        $queue = config('queue.connections.'.config('queue.default'), []);
        $async = in_array($queue['driver'] ?? null, ['database', 'redis', 'sqs', 'beanstalkd'], true);
        $add('queue', $async ? 'ok' : 'error', $async ? 'An asynchronous queue is configured; worker execution is not verified.' : 'Configure a supported asynchronous queue before enabling message alerts.');
        if ($async) {
            if (($queue['driver'] ?? null) === 'sqs') {
                $add('queue_timeout', 'warning', 'Verify the external visibility timeout exceeds the longest job timeout (message push: 30 seconds).');
            } else {
                $safe = (int) ($queue['retry_after'] ?? 0) > 30;
                $add('queue_timeout', $safe ? 'ok' : 'error', $safe ? 'Queue retry_after exceeds the 30-second push job timeout.' : 'Queue retry_after must exceed the 30-second push job timeout.');
            }
        }
        $https = parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https';
        $add('https', $https ? 'ok' : 'warning', $https ? 'HTTPS app URL configured; certificate trust and phone access are not verified.' : 'Configure trusted HTTPS for phones; localhost browser exceptions are development-only.');
        $enabled = (bool) config('remember.push.enabled');
        $add('push', ! $enabled ? 'warning' : ($push->available() ? 'ok' : 'error'), ! $enabled ? 'Web Push is disabled.' : ($push->available() ? 'VAPID settings are present; provider delivery is not verified.' : 'Web Push is enabled but VAPID or queue configuration is invalid.'));
        $reverb = config('broadcasting.default') === 'reverb';
        if ($reverb) {
            $valid = config('remember.realtime.enabled') && filled(config('broadcasting.connections.reverb.key'))
                && filled(config('broadcasting.connections.reverb.secret')) && filled(config('broadcasting.connections.reverb.app_id'))
                && filled(config('remember.realtime.host')) && in_array(config('remember.realtime.scheme'), ['http', 'https'], true);
            $add('realtime', $valid ? 'ok' : 'error', $valid ? 'Reverb settings are present; socket connectivity is not verified.' : 'Reverb is selected but required app/browser settings are incomplete.');
            if ($https && config('remember.realtime.scheme') !== 'https') {
                $add('realtime_tls', 'error', 'An HTTPS app requires an HTTPS/WSS browser-facing Reverb endpoint.');
            }
            $origins = config('reverb.apps.apps.0.allowed_origins', []);
            $add('realtime_origins', $origins !== [] && ! in_array('*', $origins, true) ? 'ok' : 'error', $origins !== [] && ! in_array('*', $origins, true) ? 'Reverb has explicit allowed origins.' : 'Set explicit Reverb allowed origins before enabling sockets.');
        } else {
            $add('realtime', 'warning', 'Reverb is disabled; open pages use polling fallback.');
        }
        $add('image_retention', $images->retentionHours() !== null ? 'ok' : 'error', $images->retentionHours() !== null ? 'New image retention is '.$images->retentionHours().' hours; existing uploads retain their stored expiry.' : 'Image retention must be a positive whole number of hours up to 87600.');
        $bucket = config('filesystems.disks.message_gcs.bucket');
        $add('image_storage', $images->available() ? 'ok' : (filled($bucket) ? 'error' : 'warning'), $images->available() ? 'A separate private GCS disk is configured; bucket IAM, retention and connectivity are not verified.' : (filled($bucket) ? 'Message image disk configuration is unsafe or incomplete; uploads are disabled.' : 'Message image bucket is not configured; uploads are disabled.'));

        $counts = [];
        try {
            $tables = ['messaging_participants', 'conversations', 'conversation_memberships', 'messages', 'message_attachments', 'message_post_shares', 'notification_preferences', 'push_subscriptions', 'message_push_deliveries'];
            $missing = array_filter($tables, fn (string $table): bool => ! Schema::hasTable($table));
            if ($missing !== []) {
                $add('database', 'error', 'Messaging tables are missing; apply the application migrations.');
            } else {
                $add('database', 'ok', 'Messaging tables are present.');
                $counts = [
                    'pending_push' => MessagePushDelivery::query()->where('state', 'pending')->count(),
                    'overdue_push' => MessagePushDelivery::query()->where('state', 'pending')->where('next_attempt_at', '<=', now()->subMinute())->count(),
                    'failed_push' => MessagePushDelivery::query()->where('state', 'failed')->count(),
                    'expired_images_pending_removal' => MessageAttachment::query()->whereNull('files_removed_at')->where('expires_at', '<=', now())->count(),
                    'image_removal_overdue_five_minutes' => MessageAttachment::query()->whereNull('files_removed_at')->where('expires_at', '<=', now()->subMinutes(5))->count(),
                ];
                $add('push_backlog', $counts['overdue_push'] || $counts['failed_push'] ? 'warning' : 'ok', 'Review overdue or failed deliveries alongside worker and scheduler monitoring.');
                $add('image_cleanup', $counts['image_removal_overdue_five_minutes'] ? 'error' : 'ok', $counts['image_removal_overdue_five_minutes'] ? 'Expired images are awaiting removal for at least five minutes; investigate cleanup immediately.' : 'No image-removal backlog older than five minutes.');
            }
        } catch (Throwable) {
            // Never render connection strings, SQL bindings or encrypted subscription values.
            $add('database', 'error', 'Messaging database status could not be read; check connectivity and migrations.');
        }
        $add('runtime', 'unverified', 'This read-only check does not verify running processes, scheduler heartbeats, GCS policies, push-service egress or physical phones.');
        $failed = collect($checks)->contains('status', 'error');
        if ($this->option('json')) {
            $this->line(json_encode(['configuration_ok' => ! $failed, 'checks' => $checks, 'counts' => $counts], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Status', 'Details'], array_map(fn (array $check): array => [$check['name'], $check['status'], $check['message']], $checks));
            foreach ($counts as $name => $count) {
                $this->line($name.': '.$count);
            }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
