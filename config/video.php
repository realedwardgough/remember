<?php

declare(strict_types=1);

return [
    'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
    'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),
    'max_upload_kb' => 256 * 1024,
    'timeout' => 1800,
];
