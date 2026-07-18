<?php

declare(strict_types=1);

namespace App\Enum;

enum TimelinePostType: string
{
    case EVENT = 'Event';
    case MILESTONE = 'Milestone';
    case MEMORY = 'Memory';
    case LETTER = 'Letter';

    /**
     * Returns the initial which is used on the
     * timeline posts side for the timeline visual styling
     */
    public function initial(): string
    {
        return match ($this) {
            self::EVENT => 'E',
            self::MILESTONE => 'M',
            self::MEMORY => 'F',
            self::LETTER => 'L',
        };
    }

    /**
     * Returns the classes used for the timeline posts badge, which
     * is located at the side of the post
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::EVENT => 'text-emerald-700',
            self::MILESTONE => 'text-rose-700',
            self::MEMORY => 'text-sky-700',
            self::LETTER => 'text-violet-700',
        };
    }

    /**
     * Returns the classes used for the timeline posts text
     */
    public function typeClass(): string
    {
        return match ($this) {
            self::EVENT => 'bg-emerald-50',
            self::MILESTONE => 'bg-rose-50',
            self::MEMORY => 'bg-sky-50',
            self::LETTER => 'bg-violet-50',
        };
    }
}
