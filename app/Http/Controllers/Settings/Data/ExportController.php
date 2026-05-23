<?php

namespace App\Http\Controllers\Settings\Data;

use App\Http\Controllers\Settings\SettingsController;
use App\Jobs\Settings\GenerateDataExport;
use App\Models\DataExport;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Data exports queue. The Job assembles the file off the request
 * thread; this controller stages the row and lists outcomes.
 */
class ExportController extends SettingsController
{
    public const TYPES = ['events', 'attendees', 'tickets', 'financials', 'all'];

    public function index(Request $request): Response
    {
        $org = $this->org($request, 'data.export-request');

        $exports = DataExport::query()
            ->where('organization_id', $org->id)
            ->with('requester:id,name,email')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (DataExport $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'format' => $e->format,
                'delivery' => $e->delivery,
                'date_from' => $e->date_from?->toIso8601String(),
                'date_to' => $e->date_to?->toIso8601String(),
                'status' => $e->status,
                'error' => $e->error,
                'requested_by' => $e->requester?->name,
                'created_at' => $e->created_at->toIso8601String(),
                'completed_at' => $e->completed_at?->toIso8601String(),
                'has_download' => $e->status === 'ready' && $e->download_path !== null,
            ]);

        return Inertia::render('settings/data/exports', [
            'exports' => $exports,
            'types' => self::TYPES,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Data exports', 'href' => '/settings/data/exports'],
            ],
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'data.export-request');

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'format' => ['required', 'in:csv,json'],
            'delivery' => ['required', 'in:email,s3'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $export = DataExport::create([
            ...$data,
            'organization_id' => $org->id,
            'requested_by_id' => $request->user()->id,
            'status' => 'queued',
        ]);

        GenerateDataExport::dispatch($export->id);

        $audit->record('data.export.requested', $org, $request->user(), 'data_export', (string) $export->id, after: $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Export queued. You\'ll be notified when it\'s ready.')]);

        return back();
    }

    public function download(Request $request, DataExport $export): StreamedResponse|BinaryFileResponse|HttpResponse
    {
        $org = $this->org($request, 'data.export-request');
        abort_if($export->organization_id !== $org->id, 403);
        abort_if($export->status !== 'ready' || $export->download_path === null, 404);

        return Storage::disk('local')->download(
            $export->download_path,
            'export-'.$export->id.'.'.$export->format,
        );
    }
}
