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
                ->with(['comment.author', 'comment.post'])
                ->orderByDesc('id')
                ->paginate(20)
                ->through(fn (ActivityNotification $notification): array => [
                    'id' => $notification->id,
                    'author' => $notification->comment->author?->name ?? 'Family',
                    'preview' => Str::limit($notification->comment->content, 160),
                    'postTitle' => $notification->comment->post->title,
                    'createdAt' => $notification->created_at->toIso8601String(),
                    'timeAgo' => $notification->created_at->diffForHumans(),
                    'read' => $notification->read_at !== null,
                ]),
        ]);
    }

    public function open(Request $request, int $notification): RedirectResponse
    {
        $activity = ActivityNotification::query()->visibleTo($request->user())
            ->with('comment')->findOrFail($notification);

        ActivityNotification::query()->whereKey($activity->id)->whereNull('read_at')
            ->update(['read_at' => now()]);

        return redirect()->to(route('posts.show', $activity->comment->post_id).'#comment-'.$activity->comment_id);
    }

    public function readAll(Request $request): RedirectResponse
    {
        ActivityNotification::query()->visibleTo($request->user())->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }
}
