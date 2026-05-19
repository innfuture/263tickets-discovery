<?php

namespace App\Enums;

enum EventVisibility: string
{
    case Public = 'public';
    case Unlisted = 'unlisted';
    case Private = 'private';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isDiscoverable(): bool
    {
        return $this === self::Public;
    }

    public function requiresInvitation(): bool
    {
        return $this === self::Private;
    }
}
