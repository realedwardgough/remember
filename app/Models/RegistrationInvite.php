<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $username
 * @property string $token_hash
 * @property int|null $accepted_by
 * @property \Carbon\CarbonImmutable|null $accepted_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\User|null $acceptedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegistrationInvite newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegistrationInvite newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegistrationInvite query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegistrationInvite whereAcceptedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegistrationInvite whereAcceptedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegistrationInvite whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegistrationInvite whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegistrationInvite whereTokenHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegistrationInvite whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RegistrationInvite whereUsername($value)
 * @mixin \Eloquent
 */
#[Fillable(['username', 'token_hash', 'accepted_by', 'accepted_at'])]
class RegistrationInvite extends Model
{
    use HasFactory;

    public static function hashToken(string $token): string
    {
        return hash(algo: 'sha256', data: $token);
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(related: User::class, foreignKey: 'accepted_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }
}
