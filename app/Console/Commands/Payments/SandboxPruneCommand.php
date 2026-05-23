<?php

declare(strict_types=1);

namespace App\Console\Commands\Payments;

use App\Models\SandboxIdempotencyKey;
use App\Models\SandboxTransaction;
use App\Models\SandboxWebhookOutbox;
use Illuminate\Console\Command;

/**
 * Sandbox retention sweep (§10). Defaults:
 *   - sandbox_transactions older than 7 days are deleted (cascades).
 *   - sandbox_webhook_outbox in delivered/dropped older than 24h.
 *   - sandbox_idempotency_keys past expires_at.
 *
 * Run nightly via the scheduler; misses are non-fatal — only disk use.
 */
class SandboxPruneCommand extends Command
{
    protected $signature = 'sandbox:prune
        {--transactions-days=7 : Drop sandbox transactions older than this many days}
        {--webhooks-hours=24 : Drop terminal sandbox webhooks older than this}';

    protected $description = 'Truncate aged sandbox transactions and webhook deliveries.';

    public function handle(): int
    {
        $txDays = max(0, (int) $this->option('transactions-days'));
        $hookHours = max(0, (int) $this->option('webhooks-hours'));

        $txDeleted = SandboxTransaction::query()
            ->where('created_at', '<', now()->subDays($txDays))
            ->delete();

        $hookDeleted = SandboxWebhookOutbox::query()
            ->whereIn('status', ['delivered', 'dropped'])
            ->where('updated_at', '<', now()->subHours($hookHours))
            ->delete();

        $keyDeleted = SandboxIdempotencyKey::query()
            ->where('expires_at', '<', now())
            ->delete();

        $this->info(sprintf(
            'Pruned: %d transactions, %d webhook events, %d idempotency keys.',
            $txDeleted, $hookDeleted, $keyDeleted,
        ));

        return self::SUCCESS;
    }
}
