<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MessageAttachment;
use App\Services\Messaging\MessageImageSettings;
use Illuminate\Console\Command;

class MessageImageLifecycle extends Command
{
    protected $signature = 'messages:image-lifecycle';
    protected $description = 'Print a GCS lifecycle backstop for review without changing any cloud resources';

    public function handle(MessageImageSettings $settings): int
    {
        $hours = $settings->retentionHours();
        if ($hours === null) {
            $this->error('MESSAGE_IMAGE_RETENTION_HOURS must be a positive integer no greater than 87600.');
            return self::FAILURE;
        }
        // Retention decreases must not let a bucket rule delete existing longer-lived uploads early.
        $hours = max($hours, (int) MessageAttachment::query()->whereNull('files_removed_at')->max('retention_hours'));
        $this->line(json_encode([
            'rule' => [['action' => ['type' => 'Delete'], 'condition' => ['age' => (int) ceil($hours / 24)]]],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
