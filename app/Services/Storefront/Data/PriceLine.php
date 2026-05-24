<?php

declare(strict_types=1);

namespace App\Services\Storefront\Data;

/**
 * One line in the breakdown returned by PriceCalculator. Rendered
 * verbatim on the checkout review screen, the receipt, and the
 * confirmation email — keep `label` short and human-readable.
 *
 * `kind` is a coarse bucket used by the UI to group lines
 * (subtotal / discount / tax / fee). It is not a free-form string;
 * see the constants for accepted values.
 */
final class PriceLine
{
    public const KIND_SUBTOTAL = 'subtotal';

    public const KIND_DISCOUNT = 'discount';

    public const KIND_TAX = 'tax';

    public const KIND_FEE = 'fee';

    public function __construct(
        public readonly string $kind,
        public readonly string $label,
        public readonly int $amountMinor,
        public readonly string $currency,
        /** @var array<string, scalar|null> */
        public readonly array $meta = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'label' => $this->label,
            'amount_cents' => $this->amountMinor,
            'currency' => $this->currency,
            'meta' => $this->meta,
        ];
    }
}
