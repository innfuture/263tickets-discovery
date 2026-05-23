<?php

namespace App\Http\Controllers\Settings\Data;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\GdprRequest;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GDPR request queue. Access-type requests trigger a per-attendee data
 * bundle (placeholder here); erasure requests mark the attendee row
 * for redaction at resolution time.
 */
class GdprRequestController extends SettingsController
{
    public function index(Request $request): Response
    {
        $org = $this->org($request, 'data.gdpr-process');

        $requests = GdprRequest::query()
            ->where('organization_id', $org->id)
            ->with('resolver:id,name')
            ->orderByDesc('submitted_at')
            ->limit(200)
            ->get()
            ->map(fn (GdprRequest $g) => [
                'id' => $g->id,
                'requester_email' => $g->requester_email,
                'type' => $g->type,
                'status' => $g->status,
                'notes' => $g->notes,
                'resolver' => $g->resolver?->name,
                'submitted_at' => $g->submitted_at?->toIso8601String(),
                'resolved_at' => $g->resolved_at?->toIso8601String(),
            ]);

        return Inertia::render('settings/data/gdpr', [
            'requests' => $requests,
            'statuses' => ['open', 'in_review', 'resolved', 'rejected'],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'GDPR requests', 'href' => '/settings/data/gdpr'],
            ],
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'data.gdpr-process');

        $data = $request->validate([
            'requester_email' => ['required', 'email', 'max:200'],
            'type' => ['required', 'in:access,erase'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $req = GdprRequest::create([
            ...$data,
            'organization_id' => $org->id,
            'status' => 'open',
            'submitted_at' => now(),
        ]);

        $audit->record('gdpr.request.opened', $org, $request->user(), 'gdpr_request', (string) $req->id, after: $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request logged.')]);

        return back();
    }

    public function update(Request $request, GdprRequest $gdpr, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'data.gdpr-process');
        abort_if($gdpr->organization_id !== $org->id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:open,in_review,resolved,rejected'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $gdpr->update([
            ...$data,
            'resolver_id' => in_array($data['status'], ['resolved', 'rejected'], true) ? $request->user()->id : null,
            'resolved_at' => in_array($data['status'], ['resolved', 'rejected'], true) ? now() : null,
        ]);

        $audit->record('gdpr.request.updated', $org, $request->user(), 'gdpr_request', (string) $gdpr->id, after: $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request updated.')]);

        return back();
    }
}
