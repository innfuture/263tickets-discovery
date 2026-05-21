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

        // Denormalised columns copied off the parent category — keeps gate
        // scanners single-row reads (no JOIN to ticket_categories needed at
        // scan time). Resolved once outside the batch loop so we don't
        // re-hit the model on every row.
        //
        // pass_type / admission_type are nullable on ticket_categories but
        // NOT NULL on offline_tickets (the gate scanner needs a concrete
        // value). Fall back to the safest defaults — single use, admit one
        // — when the category hasn't been classified.
        $organisationId = $category->organisation_id ?? $category->event?->organisation_id;
        $passType = $category->pass_type?->value ?? 'single';
        $admissionType = $category->admission_type?->value ?? 'admit_one';

        foreach ($this->batches($remaining) as $batchSize) {
            $rows = [];
            $now = now();

            for ($i = 0; $i < $batchSize; $i++) {
                $uuid = (string) Str::uuid();
                $ticketNumber = $this->generateTicketNumber($category, $uuid);
                $qrPayload = $this->signQrPayload($uuid, $ticketNumber, $category->event_id);

                // Column list MUST match the offline_tickets schema. Earlier
                // versions of this action wrote `generation_status` — that
                // column lives on ticket_categories, not offline_tickets,
                // and the bad insert failed every job silently.
                $rows[] = [
                    'uuid' => $uuid,
                    'ticket_category_id' => $category->id,
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
