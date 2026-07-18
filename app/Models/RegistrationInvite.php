<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['username', 'token_hash', 'accepted_by', 'accepted_at'])]
class RegistrationInvite extends Model
{
    use HasFactory;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
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
