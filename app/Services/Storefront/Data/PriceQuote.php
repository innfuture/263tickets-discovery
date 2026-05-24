<?php

declare(strict_types=1);

namespace App\Services\Storefront\Data;

/**
 * Snapshot of pricing for a CheckoutSession after running it through
 * the PriceCalculator pipeline.
 *
 * Lines are append-only during the run — the calculator constructs an
 * initial quote with subtotal, then hands it to discount → tax → fee
 * rules in order, each of which returns a `withLine(…)` clone.
 *
 * `totalMinor` is recomputed from the lines at quote time; don't
 * mutate the int directly.
 */
final class PriceQuote
{
    /**
     * @param  list<PriceLine>  $lines
     */
    public function __construct(
        public readonly int $subtotalMinor,
        public readonly int $discountMinor,
        public readonly int $taxMinor,
        public readonly int $feeMinor,
        public readonly int $totalMinor,
        public readonly string $currency,
        public readonly array $lines,
    ) {}

    public static function empty(string $currency): self
    {
        return new self(0, 0, 0, 0, 0, $currency, []);
    }

    public function withLine(PriceLine $line): self
    {
        $lines = $this->lines;
        $lines[] = $line;

        $subtotal = $this->subtotalMinor;
        $discount = $this->discountMinor;
        $tax = $this->taxMinor;
        $fee = $this->feeMinor;

        match ($line->kind) {
            PriceLine::KIND_SUBTOTAL => $subtotal += $line->amountMinor,
            PriceLine::KIND_DISCOUNT => $discount += $line->amountMinor,
            PriceLine::KIND_TAX => $tax += $line->amountMinor,
            PriceLine::KIND_FEE => $fee += $line->amountMinor,
            default => null,
        };

        $total = max(0, $subtotal - $discount + $tax + $fee);

        return new self($subtotal, $discount, $tax, $fee, $total, $this->currency, $lines);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'subtotal_cents' => $this->subtotalMinor,
            'discount_cents' => $this->discountMinor,
            'tax_cents' => $this->taxMinor,
            'fee_cents' => $this->feeMinor,
            'total_cents' => $this->totalMinor,
            'currency' => $this->currency,
            'lines' => array_map(fn (PriceLine $l) => $l->toArray(), $this->lines),
        ];
    }
}
