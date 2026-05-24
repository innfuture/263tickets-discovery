<?php

declare(strict_types=1);

namespace App\Services\Storefront\Data;

/**
 * What the public API hands the TicketReservation service when the
 * buyer adds tickets to the cart. The reservation service is what
 * validates inventory + caps; this DTO is just the transport.
 */
final class CartLineInput
{
    public function __construct(
        public readonly int $ticketCategoryId,
        public readonly int $quantity,
    ) {}
}
