<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'post_id',
    'disk',
    'path',
    'thumbnail_path',
    'original_name',
    'mime_type',
    'size',
    'width',
    'height',
    'metadata',
    'sort_order',
])]
#[Table(name: 'media')]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::tags(names: 'posts')->flush();
        });

        static::deleted(function (): void {
            Cache::tags(names: 'posts')->flush();
        });
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
