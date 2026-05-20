<?php

namespace App\Actions\Tickets;

use App\Enums\TicketGenerationStatus;
use App\Models\TicketCategory;
use Illuminate\Support\Str;

class GenerateOfflineTicketsAction
{
    private const BATCH_SIZE = 50;

    /**
     * Generate unique offline tickets for the given category.
     * Updates `generation_progress` in percent after each batch.
     */
    public function execute(TicketCategory $category): void
    {
        $total = $category->offline_quantity;

        if ($total <= 0) {
            $category->update([
                'generation_status' => TicketGenerationStatus::Completed,
                'generation_progress' => 100,
            ]);
            return;
        }

        $category->update([
            'generation_status' => TicketGenerationStatus::Processing,
            'generation_progress' => 0,
        ]);

        $generated = $category->offlineTickets()->count();
        $remaining = $total - $generated;

        if ($remaining <= 0) {
            $category->update([
                'generation_status' => TicketGenerationStatus::Completed,
                'generation_progress' => 100,
            ]);
            return;
        }

        $done = $generated;

        foreach ($this->batches($remaining) as $batchSize) {
            $rows = [];
            $now = now();

            for ($i = 0; $i < $batchSize; $i++) {
                $uuid = (string) Str::uuid();
                $ticketNumber = $this->generateTicketNumber($category, $uuid);
                $qrPayload = $this->signQrPayload($uuid, $ticketNumber, $category->event_id);

                $rows[] = [
                    'uuid' => $uuid,
                    'ticket_number' => $ticketNumber,
                    'ticket_category_id' => $category->id,
                    'event_id' => $category->event_id,
                    'qr_payload' => $qrPayload,
                    'generation_status' => TicketGenerationStatus::Completed->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            \App\Models\OfflineTicket::insert($rows);

            $done += $batchSize;
            $progress = (int) round(($done / $total) * 100);

            $category->update(['generation_progress' => min($progress, 99)]);
        }

        $category->update([
            'generation_status' => TicketGenerationStatus::Completed,
            'generation_progress' => 100,
        ]);
    }

    /** @return \Generator<int, int> */
    private function batches(int $total): \Generator
    {
        $remaining = $total;

        while ($remaining > 0) {
            $size = min($remaining, self::BATCH_SIZE);
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
