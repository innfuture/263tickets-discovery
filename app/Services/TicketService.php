<?php

namespace App\Services;

use App\Enums\TicketGenerationStatus;
use App\Jobs\GenerateOfflineTicketsJob;
use App\Models\Event;
use App\Models\TicketCategory;
use App\Models\TicketCurrencyPrice;
use App\Models\TicketDiscount;
use App\Models\TicketPromoCode;
use Illuminate\Support\Facades\DB;

class TicketService
{
    /**
     * Create a ticket category and dispatch background generation for offline tickets.
     *
     * @param array<string, mixed> $data Validated data from StoreTicketCategoryRequest
     */
    public function createCategory(Event $event, array $data): TicketCategory
    {
        return DB::transaction(function () use ($event, $data) {
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

            // Persist per-currency price overrides
            if (! empty($currencyPrices)) {
                foreach ($currencyPrices as $entry) {
                    TicketCurrencyPrice::create([
                        'ticket_category_id' => $category->id,
                        'currency_code' => strtoupper($entry['currency_code']),
                        'price' => $entry['price'],
                    ]);
                }
            }

            // Dispatch background generation if there are offline tickets
            if ($offlineQty > 0) {
                GenerateOfflineTicketsJob::dispatch($category);
            }

            return $category;
        });
    }

    /**
     * Update a ticket category. Dispatches additional offline ticket generation
     * if offline_quantity was increased.
     *
     * @param array<string, mixed> $data
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

            // Only regenerate if quantity was actually increased
            if ($newOffline > $previousOffline) {
                $category->update([
                    'generation_status' => TicketGenerationStatus::Pending,
                    'generation_progress' => 0,
                ]);
                GenerateOfflineTicketsJob::dispatch($category->fresh());
            }

            return $category->fresh();
        });
    }

    /**
     * Completely remove a category and all its tickets.
     * This is a destructive operation — should only be called if no tickets have been sold.
     */
    public function deleteCategory(TicketCategory $category): void
    {
        DB::transaction(function () use ($category) {
            $category->offlineTickets()->forceDelete();
            $category->currencyPrices()->delete();
            $category->discounts()->delete();
            $category->promoCodes()->delete();
            $category->forceDelete();
        });
    }

    // ─── Discount helpers ────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    public function createDiscount(TicketCategory $category, array $data): TicketDiscount
    {
        return TicketDiscount::create([
            ...$data,
            'ticket_category_id' => $category->id,
        ]);
    }

    /**
     * @param array<string, mixed> $data
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
