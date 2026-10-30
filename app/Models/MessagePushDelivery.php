<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['message_id', 'push_subscription_id', 'state', 'attempts', 'next_attempt_at', 'expires_at'])]
class MessagePushDelivery extends Model
{
    protected function casts(): array
    {
        return ['attempts' => 'integer', 'next_attempt_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
