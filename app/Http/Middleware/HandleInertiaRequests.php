<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enum\TimelinePostType;
use App\Models\Timeline;
use App\Queries\GetSidebarData;
use App\Queries\GetUnreadMessageCount;
use App\Queries\GetUnreadNotificationCount;
use App\Services\Messaging\MessageImageSettings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $sidebar = null;
        $sidebarData = function () use ($request, &$sidebar): array {
            return $sidebar ??= app(GetSidebarData::class)->handle($request->user());
        };

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'turnstile' => [
                'enabled' => (bool) config('security.turnstile.enabled'),
                'siteKey' => config('security.turnstile.enabled') ? config('security.turnstile.site_key') : null,
            ],
            'version' => config('app.version'),
            'timeline' => fn (): array => $this->timeline(),
            'auth' => ['user' => $request->user()],
            'remember' => [
                'emailNotificationsEnabled' => (bool) config('remember.email_notifications'),
            ],
            'unreadMessagesCount' => fn (): int => $request->user()
                ? app(GetUnreadMessageCount::class)->handle($request->user()) : 0,
            'unreadNotificationsCount' => fn (): int => $request->user()
                ? app(GetUnreadNotificationCount::class)->handle($request->user()) : 0,
            'messagingRealtime' => fn (): array => $request->user()
                ? config('remember.realtime') : ['enabled' => false],
            'messageImages' => fn (): array => $request->user()
                ? app(MessageImageSettings::class)->publicSettings() : ['enabled' => false],
            'flash' => [
                'inviteUrl' => fn (): ?string => session('inviteUrl'),
            ],
            'filters' => fn (): array => $this->filters($request),
            'postTypes' => fn (): array => array_map(
                static fn (TimelinePostType $type): string => $type->value,
                TimelinePostType::cases(),
            ),
            'tags' => fn (): array => $request->user() ? $sidebarData()['tags'] : [],
            'family' => fn (): array => $request->user() ? $sidebarData()['family'] : [],
            'family_count' => fn (): int => $request->user() ? $sidebarData()['family_count'] : 0,
            'media_count' => fn (): int => $request->user() ? $sidebarData()['media_count'] : 0,
            'memories_count' => fn (): int => $request->user() ? $sidebarData()['memories_count'] : 0,
        ];
    }

    /** @return array{name: string, description: ?string} */
    private function timeline(): array
    {
        $timeline = auth()->check() ? Timeline::query()->first() : null;

        return [
            'name' => $timeline?->name ?? (string) config('app.name'),
            'description' => $timeline?->description,
        ];
    }

    /** @return array{search: string, tag: string, type: string, author: string} */
    private function filters(Request $request): array
    {
        $type = $request->string('type')->trim()->toString();

        return [
            'search' => $request->string('search')->trim()->toString(),
            'tag' => $request->string('tag')->trim()->lower()->toString(),
            'type' => TimelinePostType::tryFrom($type)?->value ?? '',
            'author' => $request->string('author')->trim()->lower()->toString(),
        ];
    }

}
