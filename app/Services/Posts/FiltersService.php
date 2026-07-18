<?php

declare(strict_types=1);

namespace App\Services\Posts;

use App\Enum\TimelinePostType;
use Illuminate\Http\Request;

class FiltersService
{
    /**
     * @return array{search: string, tag: string, type: string, author: string}
     */
    public function set(Request $request): array
    {
        $type = $request->string(key: 'type')
            ->trim()
            ->toString();

        return [
            'search' => $request->string(key: 'search')
                ->trim()
                ->toString(),

            'tag' => $request->string(key: 'tag')
                ->trim()
                ->lower()
                ->toString(),

            'type' => TimelinePostType::tryFrom(value: $type)?->value ?? '',

            'author' => $request->string(key: 'author')
                ->trim()
                ->lower()
                ->toString(),
        ];
    }
}
