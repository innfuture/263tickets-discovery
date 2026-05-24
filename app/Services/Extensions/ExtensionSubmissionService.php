<?php

declare(strict_types=1);

namespace App\Services\Extensions;

use App\Models\Extension;
use App\Models\ExtensionDeveloper;
use App\Models\ExtensionVersion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Developer-side submission pipeline. Three operations:
 *
 *   submit($developer, $manifest, $signature)
 *       Validates the manifest, verifies the signature with the
 *       developer's pinned public key, creates / updates the
 *       Extension row + a `pending_review` ExtensionVersion row.
 *
 *   approve($version, $reviewer, $notes)
 *       Marketplace reviewer ratifies → version becomes installable.
 *
 *   publish($version)
 *       Promotes an `approved` version to `published`. New installs
 *       pick this up; existing installs stay on their pinned version
 *       until the org admin upgrades.
 */
class ExtensionSubmissionService
{
    public function __construct(
        protected ExtensionManifestValidator $validator,
        protected ExtensionSigner $signer,
    ) {}

    /** @param array<string, mixed> $rawManifest */
    public function submit(ExtensionDeveloper $developer, array $rawManifest, string $signatureBase64): ExtensionVersion
    {
        $manifest = $this->validator->validate($rawManifest);

        if (! $this->signer->verify($developer, $manifest, $signatureBase64)) {
            throw new RuntimeException('Manifest signature did not verify against developer public key.');
        }

        return DB::transaction(function () use ($developer, $manifest, $signatureBase64) {
            $extension = Extension::query()
                ->where('extension_developer_id', $developer->id)
                ->where('slug', $manifest['slug'])
                ->first();

            if (! $extension) {
                $extension = Extension::create([
                    'extension_developer_id' => $developer->id,
                    'slug' => $manifest['slug'],
                    'name' => $manifest['name'],
                    'description' => $manifest['description'] ?? null,
                    'icon_url' => $manifest['icon_url'] ?? null,
                    'category' => $manifest['category'] ?? null,
                    'tags' => $manifest['tags'] ?? [],
                    'homepage_url' => $manifest['homepage_url'] ?? null,
                    'status' => Extension::STATUS_PENDING,
                ]);
            }

            // One pending version per (extension, version). Repeated
            // submits replace the manifest + signature so the
            // developer can fix review issues without bumping the
            // version number.
            $existing = ExtensionVersion::query()
                ->where('extension_id', $extension->id)
                ->where('version', $manifest['version'])
                ->first();

            if ($existing && $existing->status === ExtensionVersion::STATUS_PUBLISHED) {
                throw new RuntimeException('Cannot resubmit an already-published version. Bump the version number.');
            }

            $payload = [
                'extension_id' => $extension->id,
                'version' => $manifest['version'],
                'manifest' => $manifest,
                'declared_permissions' => (array) ($manifest['permissions'] ?? []),
                'signature' => $signatureBase64,
                'status' => ExtensionVersion::STATUS_PENDING_REVIEW,
                'review_notes' => null,
                'reviewed_at' => null,
                'published_at' => null,
            ];

            if ($existing) {
                $existing->fill($payload)->save();

                return $existing->fresh();
            }

            return ExtensionVersion::create($payload);
        });
    }

    public function approve(ExtensionVersion $version, ?string $notes = null): ExtensionVersion
    {
        $version->forceFill([
            'status' => ExtensionVersion::STATUS_APPROVED,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ])->save();

        $extension = $version->extension;
        if ($extension && $extension->status !== Extension::STATUS_APPROVED) {
            $extension->forceFill([
                'status' => Extension::STATUS_APPROVED,
                'first_approved_at' => $extension->first_approved_at ?? now(),
            ])->save();
        }

        return $version;
    }

    public function reject(ExtensionVersion $version, string $reason): ExtensionVersion
    {
        $version->forceFill([
            'status' => ExtensionVersion::STATUS_REJECTED,
            'reviewed_at' => now(),
            'review_notes' => $reason,
        ])->save();

        return $version;
    }

    public function publish(ExtensionVersion $version): ExtensionVersion
    {
        if ($version->status !== ExtensionVersion::STATUS_APPROVED) {
            throw new RuntimeException('Version must be approved before publishing.');
        }
        $version->forceFill([
            'status' => ExtensionVersion::STATUS_PUBLISHED,
            'published_at' => now(),
        ])->save();

        return $version;
    }
}
