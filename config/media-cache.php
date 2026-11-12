<?php

declare(strict_types=1);

return [
    'enabled' => env('MEDIA_CACHE_ENABLED', true),
    'path' => storage_path('app/media-cache'),
    'max_bytes' => (int) env('MEDIA_CACHE_MAX_BYTES', 256 * 1024 * 1024),
    'max_file_bytes' => (int) env('MEDIA_CACHE_MAX_FILE_BYTES', 16 * 1024 * 1024),
];
