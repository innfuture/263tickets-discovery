<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ScannerCapability;
use App\Models\Event;
use App\Models\ScannerDevice;
use App\Models\ScannerProfile;
use App\Services\Audit\AuditLogger;
use App\Services\Scanning\PairingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin surface for managing scanner profiles + paired devices.
 *
 * The mobile / kiosk app never talks to this controller — it lives at
 * /api/v1/scanning/* with bearer auth. This is the web UI the
 * organizer uses to create profiles, generate pairing codes, see
 * which devices are connected, and revoke them.
 *
 * Permission: `ticket.scan` to view, `ticket.scan` + organization
 * membership to mutate. We don't require a separate scanner.manage
 * permission today — anyone allowed to scan is allowed to wire up
 * scanners. Add a dedicated permission later if that opens a gap.
 */
class ScannersController extends Controller
{
    public function __construct(protected PairingService $pairing) {}

    public function index(Request $request, string $current_organization): Response
    {
        abort_unless($request->user()->can('ticket.scan'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $profiles = ScannerProfile::query()
            ->where('organisation_id', $org->uuid)
            ->withCount(['devices', 'scanEvents'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (ScannerProfile $p) => [
                'uuid' => $p->uuid,
                'name' => $p->name,
                'description' => $p->description,
                'status' => $p->status,
                'capabilities' => (array) $p->capabilities,
                'allowed_event_ids' => (array) $p->allowed_event_ids,
                'max_scans_per_minute' => (int) $p->max_scans_per_minute,
                'duplicate_window_seconds' => (int) $p->duplicate_window_seconds,
                'webhook_url' => $p->webhook_url,
                'has_webhook_secret' => ! empty($p->webhook_secret),
                'devices_count' => (int) $p->devices_count,
                'scans_count' => (int) $p->scan_events_count,
                'created_at' => $p->created_at?->toIso8601String(),
            ])
            ->all();

        $events = Event::query()
            ->where('organisation_id', $org->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Event $e) => ['value' => (int) $e->id, 'label' => $e->name])
            ->all();

        return Inertia::render('scanners/index', [
            'profiles' => $profiles,
            'event_options' => $events,
            'capability_options' => ScannerCapability::all(),
            'default_capabilities' => ScannerCapability::defaults(),
            'breadcrumbs' => [
                ['title' => 'Scanners', 'href' => "/{$current_organization}/scanners"],
            ],
        ]);
    }

    public function show(Request $request, string $current_organization, ScannerProfile $profile): Response
    {
        abort_unless($request->user()->can('ticket.scan'), 403);
        $org = $request->user()->currentOrganization;
        abort_if($org === null || $profile->organisation_id !== $org->uuid, 404);

        $devices = $profile->devices()
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn (ScannerDevice $d) => [
                'uuid' => $d->uuid,
                'label' => $d->device_label,
                'platform' => $d->platform,
                'app_version' => $d->app_version,
                'token_prefix' => $d->token_prefix,
                'last_seen_at' => $d->last_seen_at?->toIso8601String(),
                'last_known_ip' => $d->last_known_ip,
                'last_known_lat' => $d->last_known_lat,
                'last_known_lng' => $d->last_known_lng,
                'status' => $d->status,
                'revoked_at' => $d->revoked_at?->toIso8601String(),
                'revoked_reason' => $d->revoked_reason,
            ])
            ->all();

        $pendingCodes = $profile->pairingCodes()
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderBy('expires_at')
            ->get(['code', 'expires_at', 'hint_label'])
            ->map(fn ($c) => [
                'code' => $c->code,
                'expires_at' => $c->expires_at?->toIso8601String(),
                'hint_label' => $c->hint_label,
            ])
            ->all();

        $recentScans = $profile->scanEvents()
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn ($s) => [
                'uuid' => $s->uuid,
                'payload' => $s->payload,
                'verdict' => $s->verdict,
                'reason_code' => $s->reason_code,
                'was_admitted' => (bool) $s->was_admitted,
                'created_at' => $s->created_at?->toIso8601String(),
                'flags' => $s->fraud_flags ?? [],
            ])
            ->all();

        return Inertia::render('scanners/show', [
            'org_uuid' => $org->uuid,
            'profile' => [
                'uuid' => $profile->uuid,
                'name' => $profile->name,
                'description' => $profile->description,
                'status' => $profile->status,
                'capabilities' => (array) $profile->capabilities,
                'allowed_event_ids' => (array) $profile->allowed_event_ids,
                'ip_allowlist' => (array) $profile->ip_allowlist,
                'max_scans_per_minute' => (int) $profile->max_scans_per_minute,
                'duplicate_window_seconds' => (int) $profile->duplicate_window_seconds,
                'webhook_url' => $profile->webhook_url,
                'has_webhook_secret' => ! empty($profile->webhook_secret),
            ],
            'devices' => $devices,
            'pending_codes' => $pendingCodes,
            'recent_scans' => $recentScans,
            'event_options' => Event::query()
                ->where('organisation_id', $org->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Event $e) => ['value' => (int) $e->id, 'label' => $e->name])
                ->all(),
            'capability_options' => ScannerCapability::all(),
            'breadcrumbs' => [
                ['title' => 'Scanners', 'href' => "/{$current_organization}/scanners"],
                ['title' => $profile->name, 'href' => "/{$current_organization}/scanners/{$profile->uuid}"],
            ],
        ]);
    }

    public function store(Request $request, string $current_organization, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('ticket.scan'), 403);
        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $data = $this->validateProfile($request);

        $profile = ScannerProfile::create([
            'organisation_id' => $org->uuid,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => 'active',
            'capabilities' => $data['capabilities'],
            'allowed_event_ids' => $data['allowed_event_ids'] ?? [],
            'ip_allowlist' => $data['ip_allowlist'] ?? [],
            'max_scans_per_minute' => $data['max_scans_per_minute'] ?? 60,
            'duplicate_window_seconds' => $data['duplicate_window_seconds'] ?? 10,
            'webhook_url' => $data['webhook_url'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $audit->record('scanner.profile.created', $org, $request->user(), after: ['profile' => $profile->uuid]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Scanner profile created.']);

        return redirect("/{$current_organization}/scanners/{$profile->uuid}");
    }

    public function update(Request $request, string $current_organization, ScannerProfile $profile, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('ticket.scan'), 403);
        $org = $request->user()->currentOrganization;
        abort_if($org === null || $profile->organisation_id !== $org->uuid, 404);

        $data = $this->validateProfile($request, partial: true);
        $profile->fill($data)->save();

        $audit->record('scanner.profile.updated', $org, $request->user(), after: ['profile' => $profile->uuid]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profile updated.']);

        return back();
    }

    public function destroy(Request $request, string $current_organization, ScannerProfile $profile, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('ticket.scan'), 403);
        $org = $request->user()->currentOrganization;
        abort_if($org === null || $profile->organisation_id !== $org->uuid, 404);

        $profile->delete();
        $audit->record('scanner.profile.deleted', $org, $request->user(), before: ['profile' => $profile->uuid]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profile deleted.']);

        return redirect("/{$current_organization}/scanners");
    }

    public function issuePairingCode(Request $request, string $current_organization, ScannerProfile $profile, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('ticket.scan'), 403);
        $org = $request->user()->currentOrganization;
        abort_if($org === null || $profile->organisation_id !== $org->uuid, 404);

        $data = $request->validate([
            'hint_label' => ['nullable', 'string', 'max:120'],
        ]);

        $code = $this->pairing->issueCode($profile, $data['hint_label'] ?? null, $request->user());
        $audit->record('scanner.pairing.issued', $org, $request->user(), after: ['profile' => $profile->uuid, 'code' => $code->code]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Pairing code generated. Expires {$code->expires_at?->diffForHumans()}.",
        ]);

        return back();
    }

    public function revokeDevice(Request $request, string $current_organization, ScannerProfile $profile, ScannerDevice $device, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('ticket.scan'), 403);
        $org = $request->user()->currentOrganization;
        abort_if($org === null || $profile->organisation_id !== $org->uuid, 404);
        abort_if((int) $device->scanner_profile_id !== (int) $profile->id, 404);

        $reason = (string) $request->input('reason', '');
        $this->pairing->revokeDevice($device, $reason ?: null);

        $audit->record('scanner.device.revoked', $org, $request->user(), after: ['device' => $device->uuid, 'reason' => $reason]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Device revoked.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateProfile(Request $request, bool $partial = false): array
    {
        $rules = [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'in:active,disabled'],
            'capabilities' => [$partial ? 'sometimes' : 'required', 'array', 'min:1'],
            'capabilities.*' => ['string', 'in:'.implode(',', ScannerCapability::all())],
            'allowed_event_ids' => ['nullable', 'array'],
            'allowed_event_ids.*' => ['integer', 'exists:events,id'],
            'ip_allowlist' => ['nullable', 'array'],
            'ip_allowlist.*' => ['string', 'max:45'],
            'max_scans_per_minute' => ['nullable', 'integer', 'between:1,1000'],
            'duplicate_window_seconds' => ['nullable', 'integer', 'between:0,3600'],
            'webhook_url' => ['nullable', 'url', 'max:1000'],
        ];

        return $request->validate($rules);
    }
}
