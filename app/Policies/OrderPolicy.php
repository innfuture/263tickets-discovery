<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\Organization;
use App\Models\User;

/**
 * Defensive authorization layer for organizer-side access to Order
 * rows. Buyer self-service goes through magic-link auth and order
 * reference, not through this policy.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('order.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $this->memberOfOrderOrg($user, $order)
            && $user->hasPermissionTo('order.view');
    }

    public function refund(User $user, Order $order): bool
    {
        return $this->memberOfOrderOrg($user, $order)
            && $user->hasPermissionTo('order.refund');
    }

    public function void(User $user, Order $order): bool
    {
        return $this->memberOfOrderOrg($user, $order)
            && $user->hasPermissionTo('order.void');
    }

    public function update(User $user, Order $order): bool
    {
        return $this->memberOfOrderOrg($user, $order)
            && $user->hasPermissionTo('order.update');
    }

    protected function memberOfOrderOrg(User $user, Order $order): bool
    {
        $orgUuid = (string) ($order->organisation_id ?? '');
        if ($orgUuid === '') {
            return false;
        }

        $org = Organization::query()->where('uuid', $orgUuid)->first();

        return $org !== null
            && $user->organizations()->whereKey($org->id)->exists();
    }
}
