<?php

declare(strict_types=1);

namespace App\Console\Commands\Payments;

use App\Models\SandboxMerchant;
use App\Services\Payments\Sandbox\WebhookDispatcher;
use Illuminate\Console\Command;

/**
 * Scheduled worker for the sandbox outbox. In dev/CI you typically
 * flush synchronously from the test trait; in shared staging this
 * command runs every minute via the scheduler.
 */
class SandboxFlushWebhooksCommand extends Command
{
    protected $signature = 'sandbox:flush-webhooks {--merchant= : Limit to a single merchant slug}';

    protected $description = 'Deliver due sandbox webhook events.';

    public function handle(WebhookDispatcher $dispatcher): int
    {
        $merchantSlug = $this->option('merchant');
        $merchant = null;

        if ($merchantSlug) {
            $merchant = SandboxMerchant::query()->where('slug', $merchantSlug)->first();
            if ($merchant === null) {
                $this->error("Unknown merchant: {$merchantSlug}");

                return self::FAILURE;
            }
        }

        $delivered = $dispatcher->flushDue($merchant);
        $this->info("Delivered {$this->count($delivered)} sandbox webhook events.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, mixed>  $items
     */
    protected function count(array $items): int
    {
        return count($items);
    }
}
