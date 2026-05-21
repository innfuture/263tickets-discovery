<?php

namespace App\Support;

/**
 * Lightweight DTO shipped to the frontend via HandleInertiaRequests::share.
 *
 * Carries only what every page needs (identifier, name/slug, viewer's
 * role + a couple of glanceable profile bits used in the switcher and
 * sidebar). Heavy fields (description, address, social) come from
 * OrganizationController::edit on demand so we don't bloat every
 * Inertia response.
 */
readonly class UserOrganization
{
    public function __construct(
        public int $id,
        public string $uuid,
        public string $name,
        public string $slug,
        public bool $isPersonal,
        public ?string $role,
        public ?string $roleLabel,
        public ?string $logoUrl = null,
        public ?bool $isVerified = null,
        public ?bool $isCurrent = null,
    ) {
        //
    }
}
