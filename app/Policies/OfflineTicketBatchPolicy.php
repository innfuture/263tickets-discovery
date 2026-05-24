<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OfflineTicketBatch;
use App\Models\Organization;
use App\Models\User;

/**
 * Authorization for offline-ticket inventory adjustments. Membership
 * is resolved through the TicketCategory → Event → Organization chain
 * since OfflineTicketBatch itself doesn't carry organisation_id.
 */
class OfflineTicketBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('ticket_category.view');
    }

    public function view(User $user, OfflineTicketBatch $batch): bool
    {
        return $this->memberOfBatchOrg($user, $batch)
            && $user->hasPermissionTo('ticket_category.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('ticket_category.update');
    }

    public function update(User $user, OfflineTicketBatch $batch): bool
    {
        return $this->memberOfBatchOrg($user, $batch)
            && $user->hasPermissionTo('ticket_category.update');
    }

    public function cancel(User $user, OfflineTicketBatch $batch): bool
    {
        return $this->memberOfBatchOrg($user, $batch)
            && $user->hasPermissionTo('ticket_category.update');
    }

    protected function memberOfBatchOrg(User $user, OfflineTicketBatch $batch): bool
    {
        $batch->loadMissing('category.event');
        $orgUuid = (string) ($batch->category?->event?->organisation_id ?? '');
        if ($orgUuid === '') {
            return false;
        }

        $org = Organization::query()->where('uuid', $orgUuid)->first();

        return $org !== null
            && $user->organizations()->whereKey($org->id)->exists();
    }
}
