<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Post;
use App\Services\Messaging\PostPush;

class PostNotificationObserver
{
    public function created(Post $post): void
    {
        // Rows are part of the creating transaction; rollback removes them and cancels enqueueing.
        app(PostPush::class)->prepare($post);
    }
}
