<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TicketCustodyLedgerEntry;
use App\Services\Distribution\TicketCustodyLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Walks every distinct ticket_uuid in the custody ledger and verifies
 * the hash chain is intact (prev_hash linkage + this_hash recomputes
 * from canonical payload).
 *
 * Schedule: nightly. Runs in chunks of N tickets so memory stays flat
 * on multi-million-row ledgers.
 *
 * Usage:
 *   php artisan ledger:verify --chunk=500
 *   php artisan ledger:verify --since='2026-05-01'  (only tickets touched after this date)
 *   php artisan ledger:verify --bail                (stop on first violation)
 */
class VerifyCustodyLedgerIntegrity extends Command
{
    protected $signature = 'ledger:verify {--chunk=500} {--since=} {--bail}';

    protected $description = 'Verify every ticket\'s custody-ledger hash chain is intact.';

    public function handle(TicketCustodyLedger $ledger): int
    {
        $chunkSize = max(50, (int) $this->option('chunk'));
        $since = $this->option('since');
        $bail = (bool) $this->option('bail');

        $query = TicketCustodyLedgerEntry::query()->select('ticket_uuid')->distinct();
        if ($since) {
            $query->where('recorded_at', '>=', $since);
        }

        $totalChecked = 0;
        $violations = [];

        $query->orderBy('ticket_uuid')->chunk($chunkSize, function ($chunk) use ($ledger, &$totalChecked, &$violations, $bail): bool {
            foreach ($chunk as $row) {
                $totalChecked++;
                $result = $ledger->verifyChain((string) $row->ticket_uuid);
                if ($result !== null) {
                    $violations[] = [
                        'ticket_uuid' => $row->ticket_uuid,
                        'violation' => $result,
                    ];
                    Log::error('ledger.integrity_violation', [
                        'ticket_uuid' => $row->ticket_uuid,
                        'violation' => $result,
                    ]);
                    if ($bail) {
                        return false;
                    }
                }
            }

            return true;
        });

        if ($violations === []) {
            $this->info("Verified {$totalChecked} ticket chains. All intact.");
            return 0;
        }

        $this->error("Verified {$totalChecked} ticket chains. ".count($violations)." violation(s):");
        foreach (array_slice($violations, 0, 20) as $v) {
            $this->line("  {$v['ticket_uuid']} — {$v['violation']}");
        }

        return 1;
    }
}
