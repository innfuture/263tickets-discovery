<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciles the platform schema after parallel implementations landed
 * overlapping tables. Drops the duplicates we (this lineage) created
 * and keeps the canonical set the rest of the platform now uses:
 *
 *   Drop      → Keep (canonical)
 *   ──────────────────────────────────
 *   audit_events           audit_logs
 *   api_tokens             personal_api_tokens  +  api_keys
 *   api_request_logs       (stays — distinct domain, not duplicated)
 *   webhooks               organization_webhooks
 *   webhook_deliveries     organization_webhook_deliveries
 *
 * Also rolls back the per-column JSON additions on `organizations`
 * because the parallel build stores all of those under a single
 * `organization_settings.data` JSON kv-store instead.
 *
 * Idempotent: every drop is hasTable / hasColumn guarded so this can
 * land safely even if some of the duplicate tables were never created
 * on a given environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'webhook_deliveries',
            'webhooks',
            'api_tokens',
            'audit_events',
        ] as $t) {
            if (Schema::hasTable($t)) {
                Schema::drop($t);
            }
        }

        $cols = [
            'brand_kit', 'domain_settings', 'public_page_settings',
            'email_identity', 'retention_settings',
            'ticket_template_settings', 'date_format_settings',
        ];

        Schema::table('organizations', function (Blueprint $table) use ($cols) {
            foreach ($cols as $col) {
                if (Schema::hasColumn('organizations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasColumn('users', 'date_format_settings')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('date_format_settings');
            });
        }
    }

    public function down(): void
    {
        // Forward-only reconciliation. Re-introducing the dropped
        // tables would resurrect the conflict we're cleaning up.
    }
};
