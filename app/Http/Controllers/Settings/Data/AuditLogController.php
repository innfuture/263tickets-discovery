<?php

namespace App\Http\Controllers\Settings\Data;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Filterable, paginated audit log viewer + CSV export. Rows come from
 * `App\Services\Audit\AuditLogger` calls across the settings layer.
 */
class AuditLogController extends SettingsController
{
    public function index(Request $request): Response
    {
        $org = $this->org($request, 'audit_log.view');

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'actor_type' => ['nullable', 'in:user,api,system'],
            'resource_type' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = AuditLog::query()
            ->where('organization_id', $org->id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        if (! empty($filters['q'])) {
            $term = $filters['q'];
            $query->where(fn ($q) => $q
                ->where('action', 'like', "%{$term}%")
                ->orWhere('resource_type', 'like', "%{$term}%")
                ->orWhere('resource_id', 'like', "%{$term}%"));
        }
        if (! empty($filters['actor_type'])) {
            $query->where('actor_type', $filters['actor_type']);
        }
        if (! empty($filters['resource_type'])) {
            $query->where('resource_type', $filters['resource_type']);
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to'].' 23:59:59');
        }

        $entries = $query->paginate(50)->withQueryString();

        return Inertia::render('settings/data/audit-log', [
            'entries' => [
                'data' => $entries->getCollection()->map(fn (AuditLog $e) => [
                    'id' => $e->id,
                    'action' => $e->action,
                    'resource_type' => $e->resource_type,
                    'resource_id' => $e->resource_id,
                    'actor_type' => $e->actor_type,
                    'actor_name' => $e->user?->name ?? 'System',
                    'actor_email' => $e->user?->email,
                    'ip_address' => $e->ip_address,
                    'created_at' => $e->created_at->toIso8601String(),
                    'before' => $e->before,
                    'after' => $e->after,
                ])->all(),
                'links' => $entries->linkCollection()->toArray(),
                'meta' => [
                    'current_page' => $entries->currentPage(),
                    'last_page' => $entries->lastPage(),
                    'total' => $entries->total(),
                    'per_page' => $entries->perPage(),
                ],
            ],
            'filters' => $filters,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Audit log', 'href' => '/settings/data/audit-log'],
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|HttpResponse
    {
        $org = $this->org($request, 'audit_log.export');

        $rows = AuditLog::query()
            ->where('organization_id', $org->id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(50000)
            ->cursor();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['id', 'created_at', 'actor_type', 'actor_name', 'actor_email', 'action', 'resource_type', 'resource_id', 'ip_address']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->created_at->toIso8601String(),
                    $row->actor_type,
                    $row->user?->name ?? '',
                    $row->user?->email ?? '',
                    $row->action,
                    $row->resource_type,
                    $row->resource_id,
                    $row->ip_address,
                ]);
            }
            fclose($out);
        }, 'audit-log-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
