<?php

declare(strict_types=1);

namespace App\Services\Storefront\Contracts;

use App\Models\OrderItem;

/**
 * Issues a wallet-pass for one ticket. Implementations:
 *
 *   - StubPassGenerator        Plain PDF — used by the StubPassProvider
 *                              and as the fallback when no wallet keys
 *                              are configured.
 *   - ApplePassKitGenerator    Signs a .pkpass bundle (requires Apple
 *                              Developer cert — see config/storefront.php).
 *   - GoogleWalletPassGenerator JWT-signed Google Wallet object
 *                              (requires service-account JSON).
 *
 * The wrapper service `WalletPassService` picks the right generator
 * by ?provider=apple|google|pdf and falls back to the stub.
 */
interface PassGenerator
{
    public function identifier(): string;

    public function contentType(): string;

    /**
     * Filename hint the browser uses when the pass is downloaded.
     */
    public function filename(OrderItem $item): string;

    /**
     * Raw bytes of the pass. Caller streams or attaches.
     */
    public function build(OrderItem $item): string;
}
