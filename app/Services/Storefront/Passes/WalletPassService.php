<?php

declare(strict_types=1);

namespace App\Services\Storefront\Passes;

use App\Models\OrderItem;
use App\Services\Storefront\Contracts\PassGenerator;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Picks a PassGenerator by `provider` ('apple' | 'google' | 'pdf')
 * with the stub PDF/SVG path as the universal fallback. Catches
 * config / credential errors and downgrades to the stub so the
 * buyer always gets *something* downloadable.
 */
class WalletPassService
{
    public function __construct(protected Container $container) {}

    public function generate(OrderItem $item, string $provider = 'pdf'): PassGenerator
    {
        try {
            $generator = $this->resolve($provider);
            // Validate by attempting a build (cheaper than a separate health-check).
            $generator->build($item);

            return $generator;
        } catch (Throwable) {
            return $this->container->make(StubPassGenerator::class);
        }
    }

    public function build(OrderItem $item, string $provider = 'pdf'): array
    {
        $generator = $this->resolve($provider);

        try {
            $bytes = $generator->build($item);
        } catch (Throwable) {
            $generator = $this->container->make(StubPassGenerator::class);
            $bytes = $generator->build($item);
        }

        return [
            'bytes' => $bytes,
            'content_type' => $generator->contentType(),
            'filename' => $generator->filename($item),
            'provider' => $generator->identifier(),
        ];
    }

    protected function resolve(string $provider): PassGenerator
    {
        $map = [
            'apple' => ApplePassKitGenerator::class,
            'google' => GoogleWalletPassGenerator::class,
            'pdf' => StubPassGenerator::class,
        ];
        $class = $map[$provider] ?? StubPassGenerator::class;

        return $this->container->make($class);
    }
}
