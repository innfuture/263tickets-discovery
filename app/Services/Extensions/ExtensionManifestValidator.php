<?php

declare(strict_types=1);

namespace App\Services\Extensions;

use RuntimeException;

/**
 * Manifest shape (JSON):
 *
 *   {
 *     "name": "Acme CRM Sync",
 *     "slug": "acme-crm-sync",
 *     "version": "1.4.2",
 *     "description": "Pushes paid orders to Acme CRM.",
 *     "homepage_url": "https://acme.com/extensions/crm-sync",
 *     "icon_url": "https://acme.com/icon.png",
 *     "category": "crm",
 *     "tags": ["crm", "sync"],
 *     "permissions": ["orders.read", "webhooks.subscribe"],
 *     "webhook_url": "https://hooks.acme.com/example-app",
 *     "subscribed_events": ["order.paid", "order.refunded"],
 *     "config_schema": {
 *       "type": "object",
 *       "properties": {
 *         "acme_api_key": {"type": "string", "title": "Acme API Key"}
 *       },
 *       "required": ["acme_api_key"]
 *     },
 *     "ui_extension_points": []
 *   }
 *
 * `ui_extension_points` is reserved for a future iteration where
 * extensions can mount Inertia component slots in the organizer
 * dashboard; today it's accepted but ignored.
 */
class ExtensionManifestValidator
{
    /**
     * Throws on any structural / permission violation. Returns the
     * normalized manifest on success (keys lowercased, scalar types
     * coerced where helpful).
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function validate(array $manifest): array
    {
        foreach (['name', 'slug', 'version'] as $required) {
            if (empty($manifest[$required]) || ! is_string($manifest[$required])) {
                throw new RuntimeException("Manifest missing required field: {$required}");
            }
        }

        if (! preg_match('/^[a-z0-9][a-z0-9-]{2,79}$/', (string) $manifest['slug'])) {
            throw new RuntimeException(
                'Manifest `slug` must be lowercase letters/numbers/hyphens, 3–80 chars.'
            );
        }

        if (! preg_match('/^\d+\.\d+\.\d+(-[A-Za-z0-9._-]+)?$/', (string) $manifest['version'])) {
            throw new RuntimeException('Manifest `version` must be valid semver.');
        }

        $perms = (array) ($manifest['permissions'] ?? []);
        $unknown = ExtensionPermission::unknown($perms);
        if (! empty($unknown)) {
            throw new RuntimeException(
                'Manifest declares unknown permissions: '.implode(', ', $unknown)
            );
        }

        if (! empty($manifest['webhook_url']) && ! filter_var($manifest['webhook_url'], FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Manifest `webhook_url` is not a valid URL.');
        }

        $events = (array) ($manifest['subscribed_events'] ?? []);
        $knownEvents = (array) config('automation.event_types', []);
        foreach ($events as $event) {
            if (! is_string($event) || ! in_array($event, $knownEvents, true)) {
                throw new RuntimeException("Unknown subscribed event: {$event}");
            }
        }

        return [
            'name' => (string) $manifest['name'],
            'slug' => strtolower((string) $manifest['slug']),
            'version' => (string) $manifest['version'],
            'description' => (string) ($manifest['description'] ?? ''),
            'homepage_url' => $manifest['homepage_url'] ?? null,
            'icon_url' => $manifest['icon_url'] ?? null,
            'category' => $manifest['category'] ?? null,
            'tags' => array_values((array) ($manifest['tags'] ?? [])),
            'permissions' => array_values($perms),
            'webhook_url' => $manifest['webhook_url'] ?? null,
            'subscribed_events' => array_values($events),
            'config_schema' => (array) ($manifest['config_schema'] ?? []),
            'ui_extension_points' => array_values((array) ($manifest['ui_extension_points'] ?? [])),
        ];
    }

    /**
     * Canonical JSON for signing — keys sorted, no whitespace.
     * Both the developer (signing side) and us (verification side)
     * must use this exact encoding or signatures won't match.
     *
     * @param  array<string, mixed>  $manifest
     */
    public function canonicalize(array $manifest): string
    {
        $this->sortRecursive($manifest);

        return (string) json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, mixed> $arr */
    protected function sortRecursive(array &$arr): void
    {
        ksort($arr);
        foreach ($arr as &$value) {
            if (is_array($value)) {
                $this->sortRecursive($value);
            }
        }
    }
}
