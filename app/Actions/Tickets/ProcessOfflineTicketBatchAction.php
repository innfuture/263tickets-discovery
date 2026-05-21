<?php

namespace App\Actions\Tickets;

use App\Enums\BatchOperation;
use App\Enums\BatchStatus;
use App\Enums\TicketGenerationStatus;
use App\Models\OfflineTicket;
use App\Models\OfflineTicketBatch;
use App\Models\TicketCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Batch-aware processor for offline ticket inventory changes.
 *
 * One method dispatches three operations:
 *
 *   • Create / Increase — generate `quantity` fresh OfflineTicket rows
 *     attributed to the batch, in 50-at-a-time inserts so we stay under
 *     MySQL's max-packet-size for big requests and surface progress
 *     updates per chunk.
 *
 *   • Decrease — void `quantity` existing tickets, LIFO across batches
 *     (newest batch first → least-likely-to-have-been-distributed get
 *     pulled first). Only unscanned, unsold, unvoided rows are eligible.
 *
 * Invariants:
 *   - Idempotent re-runs: re-entering with a batch already partially
 *     processed picks up where it left off rather than double-minting.
 *   - Atomic per-chunk: each insert/void chunk is its own short
 *     transaction, so a mid-batch failure leaves a consistent partial
 *     state the operator can inspect via the batch's actual_quantity.
 *   - The parent category's offline_quantity is updated to match the
 *     post-batch active count (total minted minus total voided).
 */
class ProcessOfflineTicketBatchAction
{
    private const CHUNK_SIZE = 50;

    public function execute(OfflineTicketBatch $batch): void
    {
        $batch->loadMissing('category');
        $category = $batch->category;

        if (! $category) {
            $this->markFailed($batch, 'Parent category no longer exists.');
            return;
        }

        $batch->update([
            'status' => BatchStatus::Processing,
            'started_at' => $batch->started_at ?? now(),
        ]);

        // Reflect in-flight state on the category for the UI's existing
        // polling. Done once at the top — per-chunk updates below only
        // touch generation_progress.
        $category->update([
            'generation_status' => TicketGenerationStatus::Processing,
            'generation_progress' => 0,
        ]);

        try {
            match ($batch->operation) {
                BatchOperation::Create,
                BatchOperation::Increase => $this->generate($batch, $category),
                BatchOperation::Decrease => $this->void($batch, $category),
            };

            $this->markCompleted($batch, $category);
        } catch (\Throwable $e) {
            $this->markFailed($batch, $e->getMessage());
            // Keep the category in a Failed state so the operator's polling
            // surfaces the failure rather than the spinner hanging.
            $category->update([
                'generation_status' => TicketGenerationStatus::Failed,
                'generation_progress' => null,
            ]);
            throw $e;
        }
    }

    private function generate(OfflineTicketBatch $batch, TicketCategory $category): void
    {
        $target = (int) $batch->quantity;

        // Idempotency — count what's already attributed to THIS batch so a
        // retry doesn't double-mint. Other batches' rows aren't counted.
        $alreadyMinted = OfflineTicket::query()
            ->where('batch_id', $batch->id)
            ->count();

        $remaining = $target - $alreadyMinted;

        if ($remaining <= 0) {
            return;
        }

        // Denormalised columns copied once — same rationale as the old
        // GenerateOfflineTicketsAction: gate scanners do single-row reads
        // and shouldn't need a JOIN. Defaults for nullable parents.
        $organisationId = $category->organisation_id ?? $category->event?->organisation_id;
        $passType = $category->pass_type?->value ?? 'single';
        $admissionType = $category->admission_type?->value ?? 'admit_one';

        $done = $alreadyMinted;
        foreach ($this->chunks($remaining) as $chunkSize) {
            DB::transaction(function () use (
                $batch, $category, $chunkSize, $organisationId,
                $passType, $admissionType,
            ) {
                $rows = [];
                $now = now();

                for ($i = 0; $i < $chunkSize; $i++) {
                    $uuid = (string) Str::uuid();
                    $ticketNumber = $this->generateTicketNumber($category, $uuid);
                    $qrPayload = $this->signQrPayload(
                        $uuid,
                        $ticketNumber,
                        $category->event_id,
                    );

                    $rows[] = [
                        'uuid' => $uuid,
                        'ticket_category_id' => $category->id,
                        'batch_id' => $batch->id,
                        'event_id' => $category->event_id,
                        'organisation_id' => $organisationId,
                        'ticket_number' => $ticketNumber,
                        'qr_payload' => $qrPayload,
                        'pass_type' => $passType,
                        'admission_type' => $admissionType,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                OfflineTicket::insert($rows);
            });

            $done += $chunkSize;
            $progress = (int) round(($done / $target) * 100);
            $batch->update(['progress' => min($progress, 99)]);
            $category->update(['generation_progress' => min($progress, 99)]);
        }
    }

    private function void(OfflineTicketBatch $batch, TicketCategory $category): void
    {
        $target = (int) $batch->quantity;

        // Idempotency: count what this exact batch has already voided.
        // A job retry picks up only the remainder.
        $alreadyVoided = OfflineTicket::query()
            ->where('voided_by_batch_id', $batch->id)
            ->count();

        if ($alreadyVoided >= $target) {
            return;
        }

        $reason = sprintf(
            'Voided by batch #%d (%s)',
            $batch->batch_number,
            $batch->reason ?? 'no reason provided',
        );

        // LIFO base query — newest minting batch first. The most recent
        // inventory is the least likely to have been distributed, so
        // voiding it minimises the chance of invalidating tickets that
        // are already out in the wild.
        $baseQueryFactory = function () use ($category) {
            $q = $category->offlineTickets()
                ->whereNull('deleted_at')
                ->where('is_voided', false)
                ->where('scan_count', 0);

            // Optional safety: skip offline_tickets that have already been
            // turned into an issued attendee ticket. The link column is
            // only present in some schema versions, so guard both the
            // table and the column.
            if (
                Schema::hasTable('tickets')
                && Schema::hasColumn('tickets', 'offline_ticket_id')
            ) {
                $q->whereNotIn(
                    'id',
                    DB::table('tickets')
                        ->where('ticket_category_id', $category->id)
                        ->whereNotNull('offline_ticket_id')
                        ->pluck('offline_ticket_id'),
                );
            }

            return $q;
        };

        $done = $alreadyVoided;

        while ($done < $target) {
            $chunkSize = min(self::CHUNK_SIZE, $target - $done);
            $chunkVoided = 0;

            DB::transaction(function () use (
                $baseQueryFactory, $chunkSize, $reason, $batch, &$chunkVoided,
            ) {
                $ids = $baseQueryFactory()
                    ->orderByDesc('batch_id') // LIFO across batches
                    ->orderByDesc('id')        // then newest row within
                    ->limit($chunkSize)
                    ->lockForUpdate()
                    ->pluck('id');

                if ($ids->isEmpty()) {
                    return;
                }

                OfflineTicket::whereIn('id', $ids)->update([
                    'is_voided' => true,
                    'voided_at' => now(),
                    'void_reason' => $reason,
                    'voided_by_batch_id' => $batch->id,
                ]);

                $chunkVoided = $ids->count();
            });

            if ($chunkVoided === 0) {
                // No more voidable tickets — a concurrent action drained
                // inventory below what we needed. Stop cleanly; the batch
                // will complete with actual_quantity < quantity.
                break;
            }

            $done += $chunkVoided;
            $progress = (int) round(($done / $target) * 100);
            $batch->update(['progress' => min($progress, 99)]);
        }
    }

    private function markCompleted(OfflineTicketBatch $batch, TicketCategory $category): void
    {
        // For create/increase: actual = number actually minted in this batch.
        // For decrease: actual = number actually voided (may be < quantity
        // if a concurrent action ate the available inventory).
        $actual = $batch->operation === BatchOperation::Decrease
            ? OfflineTicket::query()
                ->where('ticket_category_id', $category->id)
                ->where('is_voided', true)
                ->where('voided_at', '>=', $batch->started_at ?? $batch->created_at)
                ->count()
                - 0 // placeholder so static analyser keeps the type as int
            : OfflineTicket::query()
                ->where('batch_id', $batch->id)
                ->count();

        $batch->update([
            'status' => BatchStatus::Completed,
            'progress' => 100,
            'actual_quantity' => $actual,
            'completed_at' => now(),
        ]);

        // Recompute the category's surface count: active = generated − voided.
        $generated = OfflineTicket::query()
            ->where('ticket_category_id', $category->id)
            ->whereNull('deleted_at')
            ->count();
        $voided = OfflineTicket::query()
            ->where('ticket_category_id', $category->id)
            ->where('is_voided', true)
            ->count();

        $category->update([
            'offline_quantity' => max(0, $generated - $voided),
            'generation_status' => TicketGenerationStatus::Completed,
            'generation_progress' => 100,
        ]);
    }

    private function markFailed(OfflineTicketBatch $batch, string $message): void
    {
        $batch->update([
            'status' => BatchStatus::Failed,
            'error' => mb_substr($message, 0, 2000),
            'completed_at' => now(),
        ]);
    }

    /** @return \Generator<int, int> */
    private function chunks(int $total): \Generator
    {
        $remaining = $total;
        while ($remaining > 0) {
            $size = min($remaining, self::CHUNK_SIZE);
            yield $size;
            $remaining -= $size;
        }
    }

    private function generateTicketNumber(TicketCategory $category, string $uuid): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $category->name), 0, 4));
        $suffix = strtoupper(substr(str_replace('-', '', $uuid), 0, 8));

        return "{$prefix}-{$suffix}";
    }

    private function signQrPayload(string $uuid, string $ticketNumber, int $eventId): string
    {
        $key = config('app.key');
        $data = "{$uuid}|{$ticketNumber}|{$eventId}";
        $hmac = substr(hash_hmac('sha256', $data, $key), 0, 16);

        return base64_encode("{$data}|{$hmac}");
    }
}
