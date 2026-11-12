<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ActivityNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Notifications', [
            'activityNotifications' => fn () => ActivityNotification::query()
                ->visibleTo($request->user())
                ->with(['comment.author', 'comment.post', 'heart.user', 'heart.post'])
                ->orderByDesc('id')
                ->paginate(20)
                ->through(fn (ActivityNotification $notification): array => [
                    'id' => $notification->id,
                    'kind' => $notification->comment_id !== null ? 'comment' : 'like',
                    'author' => $notification->actor()?->name ?? 'Family',
                    'preview' => $notification->comment_id !== null ? Str::limit($notification->comment->content, 160) : null,
                    'postTitle' => $notification->relatedPost()->title,
                    'createdAt' => $notification->created_at->toIso8601String(),
                    'timeAgo' => $notification->created_at->diffForHumans(),
                    'read' => $notification->read_at !== null,
                ]),
        ]);
    }

    public function open(Request $request, int $notification): RedirectResponse
    {
        $activity = ActivityNotification::query()->visibleTo($request->user())
            ->with(['comment.post', 'heart.post'])->findOrFail($notification);

        ActivityNotification::query()->whereKey($activity->id)->whereNull('read_at')
            ->update(['read_at' => now()]);

        $anchor = $activity->comment_id !== null ? '#comment-'.$activity->comment_id : '';

        return redirect()->to(route('posts.show', $activity->relatedPost()->id).$anchor);
    }

    public function readAll(Request $request): RedirectResponse
    {
        ActivityNotification::query()->visibleTo($request->user())->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }
}
