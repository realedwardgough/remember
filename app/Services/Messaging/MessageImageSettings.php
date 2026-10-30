<?php

declare(strict_types=1);

namespace App\Services\Messaging;

class MessageImageSettings
{
    public const int MAX_ATTACHMENTS = 4;
    public const int MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
    public const int MAX_PIXELS = 20_000_000;
    public const int MAX_EDGE = 8192;

    public function retentionHours(): ?int
    {
        $hours = filter_var(config('remember.message_images.retention_hours'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 87600]]);

        return $hours === false ? null : $hours;
    }

    public function available(): bool
    {
        $disk = config('filesystems.disks.message_gcs', []);
        $bucket = $disk['bucket'] ?? '';

        return $this->retentionHours() !== null
            && ($disk['driver'] ?? null) === 'gcs'
            && ($disk['visibility'] ?? null) === 'private'
            && is_string($bucket) && trim($bucket) !== ''
            && $bucket !== config('filesystems.disks.gcs.bucket');
    }

    public function publicSettings(): array
    {
        return [
            'enabled' => $this->available(),
            'retention_hours' => $this->retentionHours(),
            'max_attachments' => self::MAX_ATTACHMENTS,
            'max_upload_bytes' => self::MAX_UPLOAD_BYTES,
        ];
    }
}
