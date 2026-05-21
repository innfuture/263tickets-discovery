<?php

namespace App\Support;

/**
 * Lightweight DTO shipped to the frontend for the sub-team layer.
 *
 * Org-level identity (logo, verified badge, etc.) lives on
 * UserOrganization — sub-teams are scoped *within* an org and carry
 * just the minimum needed to render a switcher and identify the
 * viewer's role on the team.
 */
readonly class UserTeam
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public bool $isPersonal,
        public ?string $role,
        public ?string $roleLabel,
        public ?bool $isCurrent = null,
    ) {
        //
    }
}
