<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trims the long-running event/webhook/audit tables so they don't
 * grow unbounded. Each table has its own retention window — failed
 * rows are kept longer than delivered ones so ops can investigate.
 *
 * Conservative by default; pass --aggressive to prune the delivered
 * rows after 30 days instead of 90.
 *
 * Usage:
 *   php artisan outbox:prune
 *   php artisan outbox:prune --aggressive
 *   php artisan outbox:prune --dry-run
 */
class PruneStaleOutboxRowsCommand extends Command
{
    protected $signature = 'outbox:prune {--aggressive} {--dry-run}';

    protected $description = 'Prune delivered/expired rows from outbox + delivery + scan-event tables.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $deliveredAge = $this->option('aggressive') ? 30 : 90;
        $failedAge = 365;

        $now = CarbonImmutable::now();

        $rules = [
            // (table, status_column, delivered_value, delivered_age_days, failed_age_days, ts_column)
            ['webhook_outbox', 'status', 'delivered', $deliveredAge, $failedAge, 'updated_at'],
            ['organization_webhook_deliveries', 'status_code', null, $deliveredAge, $failedAge, 'created_at'],
            ['payment_webhook_events', 'processing_status', 'processed', $deliveredAge, $failedAge, 'updated_at'],
        ];

        $total = 0;
        foreach ($rules as [$table, $statusCol, $deliveredValue, $deliveredDays, $failedDays, $tsCol]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $cut = $now->subDays($deliveredDays);
            $cutFailed = $now->subDays($failedDays);

            $deliveredQ = DB::table($table)->where($tsCol, '<', $cut);
            if ($deliveredValue !== null) {
                $deliveredQ->where($statusCol, $deliveredValue);
            } else {
                // For HTTP status codes, "delivered" = 2xx.
                $deliveredQ->whereBetween($statusCol, [200, 299]);
            }

            $failedQ = DB::table($table)->where($tsCol, '<', $cutFailed);

            if ($dry) {
                $delivered = $deliveredQ->count();
                $failed = $failedQ->count();
                $this->line("  [{$table}] would prune delivered={$delivered}, ancient_failed={$failed}");
                $total += $delivered + $failed;
                continue;
            }

            $delivered = $deliveredQ->delete();
            $failed = $failedQ->delete();
            $this->info("  [{$table}] pruned delivered={$delivered}, ancient_failed={$failed}");
            $total += $delivered + $failed;
        }

        $this->info("Done. {$total} rows ".($dry ? 'would be ' : '')."pruned.");

        return 0;
    }
}
