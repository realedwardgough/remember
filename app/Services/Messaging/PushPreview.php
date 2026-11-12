<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\Message;
use App\Models\Post;
use App\Models\ActivityNotification;
use Illuminate\Support\Str;

class PushPreview
{
    public function message(Message $message): array
    {
        $sender = $this->text($message->sender?->user?->name ?? 'Deleted User', 80);
        $body = $this->text($message->body, 240);
        if ($body === '') {
            $count = $message->attachments()->count();
            $body = $count > 0 ? ($count === 1 ? 'Sent a photo' : 'Sent '.$count.' photos')
                : ($message->sharedPost()->exists() ? 'Shared a timeline post' : 'Sent you a message');
        }
        return [
            'title' => $message->conversation->is_group ? $sender.' · '.$this->text($message->conversation->name, 80) : $sender.' · Remember', 'body' => $body, 'kind' => 'message',
            'url' => '/messages/'.$message->conversation_id, 'tag' => 'remember-conversation-'.$message->conversation_id,
        ];
    }

    public function post(Post $post): array
    {
        return [
            'title' => $this->text($post->author?->name ?? 'Deleted User', 80).' posted in Remember',
            'body' => $this->text($post->title, 240) ?: 'A new timeline post', 'kind' => 'post',
            'url' => '/posts/'.$post->id, 'tag' => 'remember-post-'.$post->id,
        ];
    }

    private function text(string $value, int $limit): string
    {
        return Str::limit(Str::squish($value), $limit);
    }

    public function activity(ActivityNotification $activity): array
    {
        $action = $activity->comment_id !== null ? ' commented on your post' : ' liked your post';

        return [
            'title' => $this->text($activity->actor()?->name ?? 'Family', 80).$action,
            'body' => $this->text($activity->relatedPost()->title, 240),
            'kind' => 'activity',
            'url' => '/notifications',
            'tag' => 'remember-activity-'.$activity->id,
        ];
    }
}
