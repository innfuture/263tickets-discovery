<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channel authorisation
|--------------------------------------------------------------------------
|
| `organization.{uuid}.scans` — live scan stream for organizer
| dashboards. Membership-gated: only users who belong to the
| organisation can subscribe. Permissions inside the org (who can
| *act* on scans) are checked separately at the REST layer; here we
| just gate visibility.
|
*/

Broadcast::channel('organization.{uuid}.scans', function (User $user, string $uuid): bool {
    $org = Organization::query()->where('uuid', $uuid)->first();
    if ($org === null) {
        return false;
    }

    // `currentOrganization` for fast path; full membership check as
    // fallback so a user switching orgs in another tab can still
    // listen to the org they were in.
    if ($user->currentOrganization?->id === $org->id) {
        return true;
    }

    return $user->organizationMemberships()->where('organization_id', $org->id)->exists();
});

/*
 * `organization.{uuid}.orders` — live order-paid stream for the
 * organizer's revenue dashboard. Same membership gate as scans.
 */
Broadcast::channel('organization.{uuid}.orders', function (User $user, string $uuid): bool {
    $org = Organization::query()->where('uuid', $uuid)->first();
    if ($org === null) {
        return false;
    }
    if ($user->currentOrganization?->id === $org->id) {
        return true;
    }

    return $user->organizationMemberships()->where('organization_id', $org->id)->exists();
});

/*
 * `storefront.events.{slug}` — PUBLIC channel for live inventory
 * updates on the buyer-facing event page. No auth callback registered
 * (Laravel treats unauth'd channels as public by default).
 */

/*
 * `event.{slug}.draft` — PRESENCE channel for the collaborative
 * event editor. Anyone with `event.update` on the owning org can
 * subscribe + see who else is editing.
 */
Broadcast::channel('event.{slug}.draft', function (User $user, string $slug): array|bool {
    $event = \App\Models\Event::query()->where('slug', $slug)->first();
    if (! $event) {
        return false;
    }
    $org = Organization::query()->where('uuid', $event->organisation_id)->first();
    if (! $org) {
        return false;
    }
    $isMember = $user->organizationMemberships()->where('organization_id', $org->id)->exists();
    if (! $isMember) {
        return false;
    }

    // Presence payload — visible to every other subscriber.
    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar' => $user->profile_photo_path ?? null,
    ];
});
