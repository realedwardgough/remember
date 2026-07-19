<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TimelineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $identifier
 * @property string $name
 * @property string|null $description
 * @property \Carbon\CarbonImmutable $setup_completed_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @method static \Database\Factories\TimelineFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timeline newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timeline newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timeline query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timeline whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timeline whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timeline whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timeline whereIdentifier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timeline whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timeline whereSetupCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Timeline whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
