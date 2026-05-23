<?php

namespace App\Http\Controllers\Settings\Developer;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\ApiKey;
use App\Models\ApiRequestLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recent API request log viewer. Rows are populated by the public-API
 * middleware (out of scope of this PR); this page renders + filters.
 */
class ApiLogController extends SettingsController
{
    public function index(Request $request): Response
    {
        $org = $this->org($request, 'api.manage-keys');

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'api_key_id' => ['nullable', 'integer'],
            'method' => ['nullable', 'in:GET,POST,PATCH,PUT,DELETE'],
            'status' => ['nullable', 'in:2xx,3xx,4xx,5xx'],
        ]);

        $query = ApiRequestLog::query()
            ->where('organization_id', $org->id)
            ->with('apiKey:id,name,prefix')
            ->orderByDesc('created_at');

        if (! empty($filters['q'])) {
            $query->where('endpoint', 'like', '%'.$filters['q'].'%');
        }
        if (! empty($filters['api_key_id'])) {
            $query->where('api_key_id', $filters['api_key_id']);
        }
        if (! empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }
        if (! empty($filters['status'])) {
            $code = (int) substr($filters['status'], 0, 1);
            $query->whereBetween('status_code', [$code * 100, $code * 100 + 99]);
        }

        $logs = $query->paginate(100)->withQueryString();

        $keys = ApiKey::query()
            ->where('organization_id', $org->id)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'prefix'])
            ->map(fn ($k) => ['id' => $k->id, 'name' => $k->name.' ('.$k->prefix.'…)']);

        return Inertia::render('settings/developer/logs', [
            'logs' => [
                'data' => $logs->getCollection()->map(fn (ApiRequestLog $l) => [
                    'id' => $l->id,
                    'endpoint' => $l->endpoint,
                    'method' => $l->method,
                    'status_code' => $l->status_code,
                    'duration_ms' => $l->duration_ms,
                    'ip_address' => $l->ip_address,
                    'api_key' => $l->apiKey ? [
                        'id' => $l->apiKey->id,
                        'name' => $l->apiKey->name,
                        'prefix' => $l->apiKey->prefix,
                    ] : null,
                    'request_preview' => $l->request_preview,
                    'response_preview' => $l->response_preview,
                    'created_at' => $l->created_at->toIso8601String(),
                ])->all(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'total' => $logs->total(),
                ],
            ],
            'filters' => $filters,
            'keys' => $keys,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'API logs', 'href' => '/settings/developer/logs'],
            ],
        ]);
    }
}
