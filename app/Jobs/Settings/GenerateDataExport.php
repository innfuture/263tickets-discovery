<?php

namespace App\Jobs\Settings;

use App\Models\DataExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Background job that assembles the requested export bundle and writes
 * it to the `local` disk under `exports/{id}.{format}`. Email + S3
 * delivery hooks are no-ops here; in production they'd hand the file
 * off to the configured delivery channel.
 */
class GenerateDataExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $exportId) {}

    public function handle(): void
    {
        $export = DataExport::query()->findOrFail($this->exportId);
        $export->update(['status' => 'processing']);

        try {
            $rows = $this->collectRows($export);
            $contents = $export->format === 'json'
                ? json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                : $this->toCsv($rows);

            $path = "exports/{$export->id}.{$export->format}";
            Storage::disk('local')->put($path, (string) $contents);

            $export->update([
                'status' => 'ready',
                'download_path' => $path,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $export->update(['status' => 'failed', 'error' => substr($e->getMessage(), 0, 255)]);
            throw $e;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectRows(DataExport $export): array
    {
        $eventIds = DB::table('events')
            ->where('organisation_id', $export->organization->uuid)
            ->when($export->date_from, fn ($q) => $q->where('created_at', '>=', $export->date_from))
            ->when($export->date_to, fn ($q) => $q->where('created_at', '<=', $export->date_to->endOfDay()))
            ->pluck('id');

        return match ($export->type) {
            'events' => DB::table('events')->whereIn('id', $eventIds)->get()->map(fn ($r) => (array) $r)->all(),
            'tickets' => DB::table('offline_tickets')->whereIn('event_id', $eventIds)->limit(50000)->get()->map(fn ($r) => (array) $r)->all(),
            'attendees' => DB::table('offline_tickets')->whereIn('event_id', $eventIds)->limit(50000)->get()->map(fn ($r) => (array) $r)->all(),
            'financials' => DB::table('invoices')->where('organization_id', $export->organization_id)->get()->map(fn ($r) => (array) $r)->all(),
            'all', default => [
                'events' => DB::table('events')->whereIn('id', $eventIds)->get()->map(fn ($r) => (array) $r)->all(),
                'tickets' => DB::table('offline_tickets')->whereIn('event_id', $eventIds)->limit(50000)->get()->map(fn ($r) => (array) $r)->all(),
                'invoices' => DB::table('invoices')->where('organization_id', $export->organization_id)->get()->map(fn ($r) => (array) $r)->all(),
            ],
        };
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function toCsv(array $rows): string
    {
        if (empty($rows)) {
            return '';
        }
        $fh = fopen('php://temp', 'r+');
        $headers = array_keys((array) reset($rows));
        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $h) {
                $v = $row[$h] ?? '';
                $line[] = is_scalar($v) ? (string) $v : json_encode($v);
            }
            fputcsv($fh, $line);
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);

        return $csv;
    }
}
