<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'device_hash', 'endpoint_hash', 'endpoint', 'public_key', 'auth_token', 'expires_at'])]
#[\Illuminate\Database\Eloquent\Attributes\Hidden(['endpoint', 'public_key', 'auth_token', 'endpoint_hash', 'device_hash'])]
class PushSubscription extends Model
{
    protected function casts(): array
    {
        return ['endpoint' => 'encrypted', 'public_key' => 'encrypted', 'auth_token' => 'encrypted', 'expires_at' => 'immutable_datetime'];
    }
}
