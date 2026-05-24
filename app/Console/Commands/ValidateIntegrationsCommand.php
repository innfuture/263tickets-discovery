<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Pre-flight check that every integration flagged as enabled has its
 * required credentials set. Run before deploy + as the first cron tick
 * after deploy so misconfiguration surfaces as a CI failure or paging
 * alert, not as a runtime exception in the user's face.
 *
 * Exit codes:
 *   0 — all good (or all disabled)
 *   1 — at least one enabled integration is missing credentials
 *   2 — at least one integration is in an inconsistent state (e.g.,
 *       enabled with partial credentials)
 *
 * Usage:
 *   php artisan integrations:validate
 *   php artisan integrations:validate --json     (machine-readable)
 *   php artisan integrations:validate --strict   (warn on any default-stub fallback in production)
 */
class ValidateIntegrationsCommand extends Command
{
    protected $signature = 'integrations:validate {--json} {--strict}';

    protected $description = 'Validate that every enabled integration has the credentials it needs.';

    public function handle(): int
    {
        $report = [];
        $hadError = false;
        $hadInconsistency = false;

        // Payments
        foreach ((array) config('payments.gateways', []) as $name => $cfg) {
            if (! ($cfg['enabled'] ?? false)) {
                continue;
            }
            $missing = $this->missingPaymentCreds((string) $name, (array) $cfg);
            $report['payments.'.$name] = [
                'enabled' => true,
                'missing' => $missing,
                'ok' => $missing === [],
            ];
            if ($missing !== []) {
                $hadError = true;
            }
        }

        // AI assistant
        if ((string) config('ai.assistant.driver', 'stub') !== 'stub') {
            $required = match (config('ai.assistant.driver')) {
                'claude' => ['ANTHROPIC_API_KEY'],
                'openai' => ['OPENAI_API_KEY'],
                default => [],
            };
            $missing = array_values(array_filter($required, fn ($k) => empty(env($k))));
            $report['ai.assistant'] = [
                'driver' => config('ai.assistant.driver'),
                'missing' => $missing,
                'ok' => $missing === [],
            ];
            if ($missing !== []) {
                $hadError = true;
            }
        }

        // Embeddings
        if ((string) config('ai.embedding.driver', 'stub') !== 'stub') {
            $missing = empty(env('OPENAI_API_KEY')) ? ['OPENAI_API_KEY'] : [];
            $report['ai.embedding'] = ['driver' => config('ai.embedding.driver'), 'missing' => $missing, 'ok' => $missing === []];
            if ($missing !== []) {
                $hadError = true;
            }
        }

        // Captcha — production-strict: NullCaptchaProvider in prod is a config smell.
        $captchaDriver = (string) config('storefront.captcha.driver', 'null');
        if ($captchaDriver === 'null' && app()->environment('production') && $this->option('strict')) {
            $report['captcha'] = ['driver' => 'null', 'ok' => false, 'note' => 'NullCaptchaProvider in production'];
            $hadInconsistency = true;
        }
        if ($captchaDriver === 'turnstile' && empty(env('TURNSTILE_SECRET_KEY'))) {
            $report['captcha'] = ['driver' => 'turnstile', 'missing' => ['TURNSTILE_SECRET_KEY'], 'ok' => false];
            $hadError = true;
        }

        // Distribution edge KV
        if (config('distribution.edge.push_activations_to_edge')) {
            $missing = [];
            foreach (['SCANNING_EDGE_KV_ACCOUNT_ID', 'SCANNING_EDGE_KV_API_TOKEN'] as $k) {
                if (empty(env($k))) {
                    $missing[] = $k;
                }
            }
            $report['distribution.edge'] = ['missing' => $missing, 'ok' => $missing === []];
            if ($missing !== []) {
                $hadError = true;
            }
        }

        // Manifest signer
        if (app()->environment('production') && empty((string) config('distribution.manifest_signing_secret', ''))) {
            $report['distribution.manifest_signer'] = ['ok' => false, 'note' => 'DISTRIBUTION_MANIFEST_SECRET blank in production'];
            $hadError = true;
        }

        // Telemetry
        $telemetryDriver = (string) config('telemetry.driver', 'null');
        if ($telemetryDriver === 'sentry' && empty(env('SENTRY_LARAVEL_DSN'))) {
            $report['telemetry'] = ['driver' => 'sentry', 'missing' => ['SENTRY_LARAVEL_DSN'], 'ok' => false];
            $hadError = true;
        }

        // SMS provider
        $smsDriver = (string) config('sms.driver', 'null');
        if ($smsDriver === 'twilio') {
            $missing = array_values(array_filter(
                ['TWILIO_ACCOUNT_SID', 'TWILIO_AUTH_TOKEN', 'TWILIO_FROM_NUMBER'],
                fn ($k) => empty(env($k)),
            ));
            $report['sms'] = ['driver' => 'twilio', 'missing' => $missing, 'ok' => $missing === []];
            if ($missing !== []) {
                $hadError = true;
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => ! $hadError && ! $hadInconsistency,
                'report' => $report,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Integration', 'Status', 'Detail'],
                array_map(fn ($name, $row) => [
                    $name,
                    ($row['ok'] ?? false) ? 'OK' : 'FAIL',
                    implode(', ', $row['missing'] ?? [$row['note'] ?? '']),
                ], array_keys($report), $report),
            );
        }

        return match (true) {
            $hadError => 1,
            $hadInconsistency => 2,
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array<int, string>
     */
    protected function missingPaymentCreds(string $name, array $cfg): array
    {
        return match ($name) {
            'stripe' => array_values(array_filter(
                ['STRIPE_SECRET_KEY', 'STRIPE_WEBHOOK_SECRET'],
                fn ($k) => empty(env($k)),
            )),
            'paynow' => array_values(array_filter(
                ['PAYNOW_INTEGRATION_ID', 'PAYNOW_INTEGRATION_KEY'],
                fn ($k) => empty(env($k)),
            )),
            'ecocash' => array_values(array_filter(
                ['ECOCASH_USERNAME', 'ECOCASH_PASSWORD', 'ECOCASH_MERCHANT_CODE'],
                fn ($k) => empty(env($k)),
            )),
            'pesepay' => array_values(array_filter(
                ['PESEPAY_INTEGRATION_KEY', 'PESEPAY_ENCRYPTION_KEY'],
                fn ($k) => empty(env($k)),
            )),
            'zimswitch' => array_values(array_filter(
                ['ZIMSWITCH_ACCESS_TOKEN', 'ZIMSWITCH_ENTITY_ID'],
                fn ($k) => empty(env($k)),
            )),
            default => [],
        };
    }
}
