<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Extensions;

use App\Http\Controllers\Controller;
use App\Models\ExtensionDeveloper;
use App\Models\ExtensionVersion;
use App\Services\Extensions\ExtensionSigner;
use App\Services\Extensions\ExtensionSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Developer-facing submission API. The simplest path:
 *
 *   1. Developer registers (fingerprint of their public key is the
 *      identity; email is contact).
 *   2. Sign your manifest.json with your private key.
 *   3. POST the manifest + signature to /developer/submissions.
 *
 * Auth model for the developer portal itself is intentionally light:
 * registration is open; submissions are gated by signature
 * verification. Manual approval gates the marketplace listing.
 */
class DeveloperPortalController extends Controller
{
    public function __construct(
        protected ExtensionSubmissionService $submissions,
        protected ExtensionSigner $signer,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'name' => ['required', 'string', 'max:191'],
            'company' => ['nullable', 'string', 'max:191'],
            'website' => ['nullable', 'url', 'max:500'],
            'public_key' => ['required', 'string'],
        ]);

        try {
            $fingerprint = $this->signer->fingerprint($validated['public_key']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'invalid_public_key', 'message' => $e->getMessage()], 422);
        }

        // Idempotent registration — re-posting the same fingerprint
        // updates contact details rather than failing.
        $developer = ExtensionDeveloper::updateOrCreate(
            ['public_key_fingerprint' => $fingerprint],
            [
                'email' => $validated['email'],
                'name' => $validated['name'],
                'company' => $validated['company'] ?? null,
                'website' => $validated['website'] ?? null,
                'public_key' => $validated['public_key'],
            ],
        );

        return response()->json([
            'data' => [
                'developer_uuid' => $developer->uuid,
                'fingerprint' => $fingerprint,
                'is_verified' => (bool) $developer->is_verified,
                'note' => 'Sign every submission with the corresponding private key.',
            ],
        ], $developer->wasRecentlyCreated ? 201 : 200);
    }

    public function submit(Request $request, string $developerUuid): JsonResponse
    {
        $developer = ExtensionDeveloper::query()->where('uuid', $developerUuid)->first();
        if (! $developer) {
            return response()->json(['error' => 'developer_not_found'], 404);
        }

        $validated = $request->validate([
            'manifest' => ['required', 'array'],
            'signature' => ['required', 'string'],
        ]);

        try {
            $version = $this->submissions->submit(
                developer: $developer,
                rawManifest: $validated['manifest'],
                signatureBase64: $validated['signature'],
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'submission_failed', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'extension_slug' => $version->extension->slug,
                'version' => $version->version,
                'status' => $version->status,
            ],
        ], 201);
    }

    public function versions(string $developerUuid): JsonResponse
    {
        $developer = ExtensionDeveloper::query()->where('uuid', $developerUuid)->first();
        if (! $developer) {
            return response()->json(['error' => 'developer_not_found'], 404);
        }

        $rows = ExtensionVersion::query()
            ->whereHas('extension', fn ($q) => $q->where('extension_developer_id', $developer->id))
            ->with('extension:id,slug,name')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (ExtensionVersion $v) => [
                'extension_slug' => $v->extension->slug,
                'extension_name' => $v->extension->name,
                'version' => $v->version,
                'status' => $v->status,
                'reviewed_at' => optional($v->reviewed_at)->toIso8601String(),
                'published_at' => optional($v->published_at)->toIso8601String(),
                'review_notes' => $v->review_notes,
            ])->all(),
        ]);
    }
}
