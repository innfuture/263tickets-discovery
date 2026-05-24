<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Extensions;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\ExtensionVersion;
use App\Services\Extensions\ExtensionPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 *   GET /api/v1/marketplace/extensions
 *   GET /api/v1/marketplace/extensions/{slug}
 *   GET /api/v1/marketplace/permissions
 *
 * Public read surface — anyone can browse what's published. Install
 * action lives behind org auth (BackOffice).
 */
class MarketplaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = Extension::query()
            ->where('status', Extension::STATUS_APPROVED)
            ->whereHas('versions', fn ($q) => $q->where('status', ExtensionVersion::STATUS_PUBLISHED))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('search'), fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%");
            }))
            ->orderByDesc('install_count')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (Extension $e) => $this->summary($e))->all(),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $ext = Extension::query()
            ->with(['developer:id,name,company,website,is_verified',
                'versions' => fn ($q) => $q->where('status', ExtensionVersion::STATUS_PUBLISHED)->orderByDesc('published_at')->limit(5)])
            ->where('slug', $slug)
            ->where('status', Extension::STATUS_APPROVED)
            ->first();

        if (! $ext) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $current = $ext->publishedVersion();

        return response()->json([
            'data' => [
                'slug' => $ext->slug,
                'name' => $ext->name,
                'description' => $ext->description,
                'icon_url' => $ext->icon_url,
                'homepage_url' => $ext->homepage_url,
                'category' => $ext->category,
                'tags' => $ext->tags,
                'install_count' => (int) $ext->install_count,
                'avg_rating' => $ext->avg_rating !== null ? (float) $ext->avg_rating : null,
                'developer' => $ext->developer ? [
                    'name' => $ext->developer->name,
                    'company' => $ext->developer->company,
                    'website' => $ext->developer->website,
                    'is_verified' => (bool) $ext->developer->is_verified,
                ] : null,
                'current_version' => $current ? [
                    'version' => $current->version,
                    'permissions' => $current->declared_permissions,
                    'subscribed_events' => $current->manifest['subscribed_events'] ?? [],
                    'config_schema' => $current->manifest['config_schema'] ?? null,
                ] : null,
                'previous_versions' => $ext->versions->map(fn ($v) => [
                    'version' => $v->version,
                    'published_at' => optional($v->published_at)->toIso8601String(),
                ])->all(),
            ],
        ]);
    }

    public function permissions(): JsonResponse
    {
        $rows = [];
        foreach (ExtensionPermission::ALL as $key) {
            $rows[] = [
                'key' => $key,
                'description' => ExtensionPermission::DESCRIPTIONS[$key] ?? '',
            ];
        }

        return response()->json(['data' => $rows]);
    }

    /** @return array<string, mixed> */
    protected function summary(Extension $ext): array
    {
        return [
            'slug' => $ext->slug,
            'name' => $ext->name,
            'description' => $ext->description,
            'icon_url' => $ext->icon_url,
            'category' => $ext->category,
            'tags' => $ext->tags,
            'install_count' => (int) $ext->install_count,
            'avg_rating' => $ext->avg_rating !== null ? (float) $ext->avg_rating : null,
        ];
    }
}
