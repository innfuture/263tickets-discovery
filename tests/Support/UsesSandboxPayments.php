<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\SandboxMerchant;
use App\Models\SandboxTransaction;
use App\Models\SandboxWebhookOutbox;
use App\Services\Payments\Drivers\SandboxGateway;
use App\Services\Payments\Sandbox\SandboxKernel;
use Illuminate\Foundation\Testing\Assert as PHPUnit;

/**
 * Pest support trait — boots the sandbox per the design spec §11.
 *
 *   uses(\Tests\Support\UsesSandboxPayments::class)->in('Feature/Payments');
 *
 * Each test gets:
 *   - sandbox driver enabled + made default
 *   - a fresh per-test merchant (parallel-safe)
 *   - assertion helpers on the test case
 *
 * Reset between tests is implicit via RefreshDatabase; we just seed
 * (§15 #6 recommendation: seed-in-trait keeps one connection).
 */
trait UsesSandboxPayments
{
    protected ?SandboxMerchant $sandboxMerchant = null;

    /**
     * Called by Pest's TestCase boot.
     */
    public function setUpUsesSandboxPayments(): void
    {
        config()->set('payments.gateways.sandbox', [
            'driver' => SandboxGateway::class,
            'enabled' => true,
            'label' => 'Sandbox (test only)',
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'ZAR', 'ZWL', 'ZiG'],
            'http' => ['timeout' => 5, 'connect_timeout' => 5, 'retries' => 0],
        ]);
        config()->set('payments.default', 'sandbox');

        $this->sandboxMerchant = SandboxMerchant::create([
            'slug' => 'test-merchant-'.bin2hex(random_bytes(4)),
            'name' => 'Test merchant',
            'emulate_default' => 'stripe',
            'default_currency' => 'USD',
            'environment' => 'test',
        ]);

        config()->set('payments.sandbox.default_merchant', $this->sandboxMerchant->slug);
    }

    /* ────────────────────── test helpers ────────────────────── */

    protected function sandboxKernel(): SandboxKernel
    {
        return app(SandboxKernel::class);
    }

    /**
     * Synchronously deliver every queued sandbox webhook regardless of
     * its scheduled_for. Equivalent to `clock->advance(seconds: huge)`
     * followed by a flush, without changing virtual time.
     */
    protected function processSandboxWebhooks(): void
    {
        SandboxWebhookOutbox::query()
            ->where('sandbox_merchant_id', $this->sandboxMerchant?->id)
            ->where('status', 'queued')
            ->update(['scheduled_for' => now()->subSecond()]);

        $this->sandboxKernel()->webhooks()->flushDue($this->sandboxMerchant);
    }

    /**
     * Fast-forward the virtual clock for this test's merchant, then
     * flush any webhooks that have become due.
     */
    protected function advanceSandboxClock(int $seconds): void
    {
        $this->sandboxKernel()->clock()->advance($this->sandboxMerchant, $seconds);
        $this->processSandboxWebhooks();
    }

    /**
     * @param  callable(array<string, mixed>): bool|null  $matcher
     */
    protected function assertSandboxWebhookSent(string $type, ?callable $matcher = null): void
    {
        $events = SandboxWebhookOutbox::query()
            ->where('sandbox_merchant_id', $this->sandboxMerchant?->id)
            ->where('type', $type)
            ->get();

        PHPUnit::assertTrue($events->isNotEmpty(), "No sandbox webhook sent of type [{$type}].");

        if ($matcher !== null) {
            PHPUnit::assertTrue(
                $events->contains(fn (SandboxWebhookOutbox $e) => $matcher((array) $e->payload)),
                "No sandbox webhook of type [{$type}] matched the predicate.",
            );
        }
    }

    protected function assertNoSandboxWebhookSent(string $type): void
    {
        $count = SandboxWebhookOutbox::query()
            ->where('sandbox_merchant_id', $this->sandboxMerchant?->id)
            ->where('type', $type)
            ->count();

        PHPUnit::assertSame(0, $count, "Expected zero sandbox webhooks of type [{$type}], got {$count}.");
    }

    protected function assertSandboxState(string $reference, string $expectedState): void
    {
        $row = SandboxTransaction::query()
            ->where('reference', $reference)
            ->where('sandbox_merchant_id', $this->sandboxMerchant?->id)
            ->first();

        PHPUnit::assertNotNull($row, "No sandbox transaction with reference [{$reference}].");
        PHPUnit::assertSame($expectedState, $row->state, "Transaction [{$reference}] state mismatch.");
    }
}
