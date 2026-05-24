<?php

namespace App\Services;

use App\Enums\BatchOperation;
use App\Enums\BatchStatus;
use App\Enums\TicketGenerationStatus;
use App\Jobs\ProcessOfflineTicketBatchJob;
use App\Models\Event;
use App\Models\OfflineTicketBatch;
use App\Models\TicketCategory;
use App\Models\TicketCurrencyPrice;
use App\Models\TicketDiscount;
use App\Models\TicketPromoCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TicketService
{
    /**
     * Create a ticket category and (if offline_quantity > 0) the first
     * batch — operation = Create, batch_number = 1. Subsequent
     * adjustments go through adjustCategoryQuantity() to keep all
     * inventory changes batch-traceable.
     *
     * @param  array<string, mixed>  $data  Validated payload from StoreTicketCategoryRequest
     */
    public function createCategory(Event $event, array $data, ?User $actor = null): TicketCategory
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            $offlineQty = (int) ($data['offline_quantity'] ?? 0);
            $currencyPrices = $data['currency_prices'] ?? null;
            $attributes = collect($data)
                ->except(['currency_prices', 'image'])
                ->all();

            $category = TicketCategory::create([
                ...$attributes,
                'event_id' => $event->id,
                'organisation_id' => $event->organisation_id,
                'generation_status' => $offlineQty > 0
                    ? TicketGenerationStatus::Pending
                    : TicketGenerationStatus::Completed,
                'generation_progress' => $offlineQty > 0 ? 0 : 100,
            ]);

            if (! empty($currencyPrices)) {
                foreach ($currencyPrices as $entry) {
                    TicketCurrencyPrice::create([
                        'ticket_category_id' => $category->id,
                        'currency_code' => strtoupper($entry['currency_code']),
                        'price' => $entry['price'],
                    ]);
                }
            }

            if ($offlineQty > 0) {
                $batch = $this->createBatch(
                    category: $category,
                    operation: BatchOperation::Create,
                    quantity: $offlineQty,
                    actor: $actor,
                    reason: 'Initial creation',
                );

                // dispatchAfterResponse() runs the job in-process after the
                // HTTP response is flushed — no `queue:work` required for
                // the local-dev path. Swap to ::dispatch() in production
                // if you run a worker.
                ProcessOfflineTicketBatchJob::dispatchAfterResponse($batch);
            }

            return $category;
        });
    }

    /**
     * Public entry point for inventory adjustments. Positive $delta = mint
     * more tickets; negative $delta = void existing ones (LIFO, newest
     * batch first, only unscanned + unsold).
     *
     * Throws DomainException when the request is unsafe (e.g. decreasing
     * by more than the available inventory). Caller should surface the
     * message to the organizer.
     */
    public function adjustCategoryQuantity(
        TicketCategory $category,
        int $delta,
        ?string $reason = null,
        ?User $actor = null,
    ): OfflineTicketBatch {
        if ($delta === 0) {
            throw new \DomainException('Adjustment delta must be non-zero.');
        }

        return DB::transaction(function () use ($category, $delta, $reason, $actor) {
            // Row-lock the category so two concurrent adjustments can't
            // race on batch_number or available-inventory calculation.
            /** @var TicketCategory $locked */
            $locked = TicketCategory::query()
                ->where('id', $category->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($delta > 0) {
                $batch = $this->createBatch(
                    category: $locked,
                    operation: BatchOperation::Increase,
                    quantity: $delta,
                    actor: $actor,
                    reason: $reason,
                );

                // Reflect the pending generation status on the category
                // so the UI's existing polling picks it up.
                $locked->update([
                    'generation_status' => TicketGenerationStatus::Pending,
                    'generation_progress' => 0,
                ]);
            } else {
                $requested = abs($delta);
                $available = $this->voidableCount($locked);

                if ($available === 0) {
                    throw new \DomainException(
                        'No tickets are available to remove — every active ticket has been sold or scanned.',
                    );
                }

                if ($requested > $available) {
                    throw new \DomainException(
                        "Cannot remove {$requested} tickets — only {$available} are unsold and unscanned.",
                    );
                }

                $batch = $this->createBatch(
                    category: $locked,
                    operation: BatchOperation::Decrease,
                    quantity: $requested,
                    actor: $actor,
                    reason: $reason,
                );
            }

            ProcessOfflineTicketBatchJob::dispatchAfterResponse($batch);

            return $batch;
        });
    }

    /**
     * Count of offline tickets that can safely be voided right now —
     * active (not soft-deleted), not already voided, never scanned, no
     * issued Ticket row pointing at them.
     */
    private function voidableCount(TicketCategory $category): int
    {
        $q = $category->offlineTickets()
            ->whereNull('deleted_at')
            ->where('is_voided', false)
            ->where('scan_count', 0);

        // If the issued `tickets` table AND its offline_ticket_id link
        // column are both present, exclude offline rows that have already
        // been turned into a real attendee ticket. Both guards are needed
        // — the table has existed in past schemas without the FK column,
        // and a SELECT against a missing column throws a 42S22.
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

        return $q->count();
    }

    /**
     * Persist a new batch row under a locked category. The caller is
     * responsible for the parent transaction + dispatching the job.
     */
    private function createBatch(
        TicketCategory $category,
        BatchOperation $operation,
        int $quantity,
        ?User $actor = null,
        ?string $reason = null,
    ): OfflineTicketBatch {
        $nextNumber = ((int) $category->batches()->max('batch_number')) + 1;

        return OfflineTicketBatch::create([
            'ticket_category_id' => $category->id,
            'batch_number' => $nextNumber,
            'operation' => $operation,
            'quantity' => $quantity,
            'actual_quantity' => 0,
            'status' => BatchStatus::Pending,
            'progress' => 0,
            'reason' => $reason,
            'actor_user_id' => $actor?->id,
        ]);
    }

    /**
     * Update a ticket category. Dispatches additional offline ticket generation
     * if offline_quantity was increased.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCategory(TicketCategory $category, array $data): TicketCategory
    {
        return DB::transaction(function () use ($category, $data) {
            $previousOffline = $category->offline_quantity;
            $newOffline = (int) ($data['offline_quantity'] ?? $previousOffline);

            $currencyPricesProvided = array_key_exists('currency_prices', $data);
            $currencyPrices = $data['currency_prices'] ?? [];

            $attributes = collect($data)
                ->except(['currency_prices', 'image'])
                ->all();

            $category->update($attributes);

            // Sync per-currency prices (replace all) — only when the key is
            // explicitly present, so partial PATCH calls don't wipe them.
            if ($currencyPricesProvided) {
                $category->currencyPrices()->delete();
                foreach ($currencyPrices as $entry) {
                    TicketCurrencyPrice::create([
                        'ticket_category_id' => $category->id,
                        'currency_code' => strtoupper($entry['currency_code']),
                        'price' => $entry['price'],
                    ]);
                }
            }

            // Quantity changes go through the batch flow so they're
            // traceable in the audit timeline. The form's
            // `offline_quantity` field is treated as an absolute target
            // here for backwards compatibility, then translated into a
            // delta batch.
            if ($newOffline !== $previousOffline) {
                try {
                    $this->adjustCategoryQuantity(
                        category: $category->fresh(),
                        delta: $newOffline - $previousOffline,
                        reason: 'Adjusted via category edit form',
                    );
                } catch (\DomainException) {
                    // Safety guard rejected the delta (e.g. decrease > available).
                    // The category itself was already saved with the
                    // user's other field changes — reset offline_quantity
                    // back to the prior value so the form input matches
                    // reality after the failed delta.
                    $category->update(['offline_quantity' => $previousOffline]);
                }
            }

            return $category->fresh();
        });
    }

    /**
     * Completely remove a category and every row that references it.
     *
     * The DB schema declares `cascadeOnDelete` on most child FKs (offline
     * tickets, currency prices, discounts, promo codes, issued tickets,
     * pricing rules) so the parent delete alone would cascade — but we
     * delete each child explicitly first as belt-and-braces:
     *
     *   1. A future migration could drop a cascade rule without anyone
     *      noticing — explicit deletes here keep the contract enforced
     *      at the service layer.
     *   2. forceDelete() bypasses soft-delete; some child tables use it
     *      and some don't, so we route through the relations to get the
     *      right semantics per model.
     *   3. The category's stored image file lives outside the DB —
     *      ImageProcessingService::delete() removes the WebP + every
     *      thumbnail variant.
     *
     * Destructive — call only after the caller has decided this is safe.
     */
    public function deleteCategory(TicketCategory $category): void
    {
        // Capture the image path before we trash the row; the file cleanup
        // happens after the DB transaction commits so we don't leave the
        // FS in a torn state if the DB delete rolls back.
        $imagePath = $category->image_path;

        DB::transaction(function () use ($category) {
            // Offline tickets — force-delete so any soft-delete trait
            // doesn't leave rows behind.
            $category->offlineTickets()->forceDelete();

            // Discount / promo / currency-price rows.
            $category->currencyPrices()->delete();
            $category->discounts()->delete();
            $category->promoCodes()->delete();

            // Raw DB::table() rather than Eloquent for these because the
            // matching models aren't present in this codebase right now
            // (only the migrations + tables are). Using the query builder
            // keeps the cascade explicit without requiring a model layer.
            // Each is wrapped in hasTable() so a future drop-table won't
            // turn this into a SQL error.
            foreach (
                [
                    'tickets',
                    'ticket_pricing_rules',
                    'ticket_price_adjustments',
                ] as $table
            ) {
                if (Schema::hasTable($table)) {
                    DB::table($table)
                        ->where('ticket_category_id', $category->id)
                        ->delete();
                }
            }

            $category->forceDelete();
        });

        // FS cleanup outside the transaction. Failure here is best-effort:
        // a stale image file is annoying but doesn't break anything.
        if ($imagePath) {
            try {
                app(ImageProcessingService::class)->delete($imagePath);
            } catch (\Throwable) {
                // swallowed — DB state is authoritative
            }
        }
    }

    // ─── Discount helpers ────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDiscount(TicketCategory $category, array $data): TicketDiscount
    {
        return TicketDiscount::create([
            ...$data,
            'ticket_category_id' => $category->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPromoCode(TicketCategory $category, array $data): TicketPromoCode
    {
        return TicketPromoCode::create([
            ...$data,
            'ticket_category_id' => $category->id,
            'code' => strtoupper(trim($data['code'])),
        ]);
    }
}
