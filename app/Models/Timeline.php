<?php

namespace App\Models;

use Database\Factories\TimelineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'description', 'setup_completed_at'])]
class Timeline extends Model
{
    /** @use HasFactory<TimelineFactory> */
    use HasFactory;

    public const string PRIMARY_IDENTIFIER = 'primary';

    /** @var array<string, mixed> */
    protected $attributes = [
        'identifier' => self::PRIMARY_IDENTIFIER,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'setup_completed_at' => 'datetime',
        ];
    }
}
