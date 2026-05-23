<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox\Signers;

class SignerRegistry
{
    /** @var array<string, class-string<SignerInterface>> */
    protected array $map = [
        'stripe' => StripeSigner::class,
        'paynow' => PaynowSigner::class,
        'adyen' => AdyenSigner::class,
    ];

    /**
     * Stand-in for emulators that don't yet have a dedicated signer.
     * Falls back to Stripe-style signing — close enough that consumer
     * code can verify with HMAC-SHA256 of the body.
     */
    public function for(string $emulate): SignerInterface
    {
        $class = $this->map[$emulate] ?? StripeSigner::class;

        return app($class);
    }

    /**
     * Register a custom signer (consumer tests or plugins).
     *
     * @param  class-string<SignerInterface>  $signer
     */
    public function register(string $emulate, string $signer): void
    {
        $this->map[$emulate] = $signer;
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->map);
    }
}
