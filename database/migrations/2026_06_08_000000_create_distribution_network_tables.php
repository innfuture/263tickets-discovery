<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical ticket distribution network — foundation schema.
 *
 * See app/Services/Distribution/README.md (and the design doc in the
 * pull request) for the full architectural rationale. In short: this
 * adds the chain-of-custody spine that turns OfflineTicket rows into
 * tracked physical artifacts moving between Backoffice → Parent
 * distributors → Child distributors → final point-of-sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('distributors')) {
            Schema::create('distributors', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('organization_id')->index();
                $table->unsignedBigInteger('parent_distributor_id')->nullable()->index();
                $table->string('name', 191);
                $table->string('slug', 191);
                // pending | active | suspended | terminated
                $table->string('status', 20)->default('pending')->index();
                // parent | child — derived from parent_distributor_id but
                // denormalised for fast WHERE filters in dashboards.
                $table->string('type', 12)->default('parent');
                $table->string('logo_path', 500)->nullable();
                $table->string('brand_color', 16)->nullable();
                $table->string('contact_name', 191)->nullable();
                $table->string('contact_email', 191)->nullable();
                $table->string('contact_phone', 32)->nullable();
                // GeoJSON polygon for the sales territory. Soft check: sales
                // outside this fence raise a geo_anomaly ledger entry but
                // are not hard-blocked (door-to-door sales would false-positive).
                $table->json('geofence_polygon')->nullable();
                $table->unsignedInteger('geofence_radius_meters')->nullable();
                $table->decimal('centroid_lat', 10, 7)->nullable();
                $table->decimal('centroid_lng', 10, 7)->nullable();
                // Nullable = inherit from org-wide default.
                $table->unsignedBigInteger('commission_model_id')->nullable();
                // Refundable float held against future leakage incidents.
                $table->unsignedBigInteger('float_deposit_cents')->default(0);
                $table->string('float_currency', 3)->default('USD');
                // Composite trust score (0-100). Drives auto-dispatch caps,
                // recomputed nightly from per-distributor metrics.
                $table->unsignedTinyInteger('trust_score')->default(50);
                $table->unsignedInteger('max_inventory_face_value_cents')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['organization_id', 'slug']);
                $table->index(['organization_id', 'type', 'status']);
            });
        }

        if (! Schema::hasTable('distributor_users')) {
            Schema::create('distributor_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('distributor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                // admin | seller | auditor
                $table->string('role', 20)->default('seller');
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->unique(['distributor_id', 'user_id']);
                $table->index(['user_id', 'revoked_at']);
            });
        }

        if (! Schema::hasTable('distributor_devices')) {
            Schema::create('distributor_devices', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('distributor_id')->constrained()->cascadeOnDelete();
                $table->string('label', 191);
                $table->string('pairing_token_hash', 64)->nullable();
                $table->timestamp('paired_at')->nullable();
                // PEM-encoded P-256 public key uploaded at pairing time.
                // Sales/transfers/voids submitted by this device must carry
                // a signature verifiable against this key. Server never
                // sees the private half — it lives in the device's secure
                // storage (Keychain / Keystore / TPM).
                $table->text('attestation_pubkey')->nullable();
                $table->string('platform', 32)->nullable();
                $table->string('app_version', 32)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->ipAddress('last_seen_ip')->nullable();
                // active | lost | retired
                $table->string('status', 16)->default('active')->index();
                $table->json('capabilities')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['distributor_id', 'status']);
            });
        }

        if (! Schema::hasTable('distributor_pairing_codes')) {
            Schema::create('distributor_pairing_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('distributor_id')->constrained()->cascadeOnDelete();
                $table->string('code_hash', 64);
                $table->string('code_prefix', 8);
                $table->string('label', 191)->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('redeemed_at')->nullable();
                $table->foreignId('redeemed_by_device_id')->nullable()
                    ->constrained('distributor_devices')->nullOnDelete();
                $table->timestamps();

                $table->index(['distributor_id', 'redeemed_at']);
                $table->index('expires_at');
            });
        }

        if (! Schema::hasTable('ticket_dispatches')) {
            Schema::create('ticket_dispatches', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                // organization | distributor — the issuer of the dispatch.
                // Backoffice → distributor uses 'organization'; inter-
                // distributor transfers use 'distributor'.
                $table->string('from_actor_type', 16);
                $table->unsignedBigInteger('from_actor_id');
                $table->foreignId('to_distributor_id')->constrained('distributors');
                // Nullable for multi-event dispatches that span SKUs.
                $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedInteger('ticket_count');
                // Binary 32B — SHA-256 root of the sorted-UUID Merkle tree.
                // Stored as hex string for portability.
                $table->string('merkle_root', 64);
                // issued | in_transit | received | disputed | cancelled
                $table->string('status', 16)->default('issued')->index();
                $table->timestamp('issued_at');
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('disputed_at')->nullable();
                // Physical tamper-evidence: seal serial number + photo hash.
                $table->string('tamper_evidence_seal_id', 64)->nullable();
                $table->string('tamper_evidence_photo_path', 500)->nullable();
                $table->string('tamper_evidence_photo_hash', 64)->nullable();
                // Detached signature over (merkle_root || recipient || timestamp).
                $table->text('manifest_signature');
                $table->string('signing_key_id', 64);
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['from_actor_type', 'from_actor_id']);
                $table->index(['to_distributor_id', 'status']);
                $table->index(['event_id', 'status']);
            });
        }

        if (! Schema::hasTable('ticket_dispatch_items')) {
            Schema::create('ticket_dispatch_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dispatch_id')->constrained('ticket_dispatches')->cascadeOnDelete();
                $table->foreignId('offline_ticket_id')->constrained('offline_tickets');
                $table->uuid('ticket_uuid');
                $table->string('merkle_leaf_hash', 64);
                // Inclusion-proof material so a subset transfer can prove
                // the leaf belongs to the parent's root without sending
                // the full manifest.
                $table->json('merkle_path')->nullable();
                $table->string('scrap_reason', 64)->nullable();
                $table->timestamps();

                $table->unique(['dispatch_id', 'offline_ticket_id'], 'dispatch_items_uniq');
                $table->index('ticket_uuid');
            });
        }

        if (! Schema::hasTable('ticket_custody_ledger')) {
            Schema::create('ticket_custody_ledger', function (Blueprint $table) {
                $table->id();
                $table->uuid('ticket_uuid')->index();
                // Per-ticket monotonic counter. Combined with ticket_uuid
                // forms the natural key for the chain. Enforced via DB
                // unique constraint so concurrent inserts can't collide.
                $table->unsignedInteger('sequence');
                // printed | dispatched | received | transferred | sold
                // | activated | scanned | voided | recovered
                // | spot_audit_ok | spot_audit_failed | geo_anomaly
                // | lifecycle_violation
                $table->string('event_type', 32)->index();
                // organization | distributor | distributor_device | system | backoffice_user
                $table->string('actor_type', 24);
                $table->unsignedBigInteger('actor_id');
                $table->json('payload');
                // hash(prev_hash || canonical_json(payload)) — 32 bytes hex.
                // Empty string for the first row of the chain.
                $table->string('prev_hash', 64);
                $table->string('this_hash', 64);
                // Detached signature by the actor's device key (POS sales,
                // distributor transfers) or by the backoffice org key
                // (dispatches, voids). Null only for system-generated rows
                // (e.g., velocity-detector anomalies).
                $table->text('signature')->nullable();
                $table->string('signing_key_id', 64)->nullable();
                $table->timestamp('occurred_at');
                $table->timestamp('recorded_at')->useCurrent();
                $table->timestamps();

                $table->unique(['ticket_uuid', 'sequence'], 'custody_uniq_chain');
                $table->index(['actor_type', 'actor_id']);
                $table->index(['event_type', 'recorded_at']);
            });
        }

        if (! Schema::hasTable('commission_models')) {
            Schema::create('commission_models', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('organization_id')->index();
                $table->string('name', 191);
                // flat_percent | tiered_volume | per_sku_rate
                // | child_passthrough_plus_margin
                $table->string('rule_type', 48);
                $table->json('rule_config');
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['organization_id', 'is_default']);
            });
        }

        if (! Schema::hasTable('distributor_inventory_snapshots')) {
            Schema::create('distributor_inventory_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('distributor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedInteger('received_count')->default(0);
                $table->unsignedInteger('transferred_in_count')->default(0);
                $table->unsignedInteger('transferred_out_count')->default(0);
                $table->unsignedInteger('sold_count')->default(0);
                $table->unsignedInteger('voided_count')->default(0);
                $table->unsignedInteger('recovered_count')->default(0);
                $table->integer('on_hand_count')->default(0);
                $table->unsignedBigInteger('gross_revenue_cents')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->timestamp('computed_at')->useCurrent();
                $table->timestamps();

                $table->unique(['distributor_id', 'event_id'], 'inv_snap_uniq');
            });
        }

        if (! Schema::hasTable('distribution_sales')) {
            Schema::create('distribution_sales', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('distributor_id')->constrained();
                $table->foreignId('distributor_device_id')->nullable()
                    ->constrained('distributor_devices')->nullOnDelete();
                $table->foreignId('offline_ticket_id')->constrained('offline_tickets');
                $table->uuid('ticket_uuid');
                $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
                $table->string('customer_phone', 32)->nullable();
                $table->string('customer_name', 191)->nullable();
                $table->string('customer_email', 191)->nullable();
                $table->unsignedBigInteger('amount_cents');
                $table->string('currency', 3)->default('USD');
                $table->decimal('gps_lat', 10, 7)->nullable();
                $table->decimal('gps_lng', 10, 7)->nullable();
                $table->boolean('outside_geofence')->default(false);
                $table->boolean('flagged_anomaly')->default(false);
                $table->json('anomaly_flags')->nullable();
                $table->timestamp('sold_at');
                $table->timestamps();

                $table->unique('ticket_uuid', 'distribution_sales_ticket_uniq');
                $table->index(['distributor_id', 'sold_at']);
                $table->index(['event_id', 'sold_at']);
            });
        }

        if (! Schema::hasTable('distributor_spot_audits')) {
            Schema::create('distributor_spot_audits', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('distributor_id')->constrained()->cascadeOnDelete();
                $table->json('challenge_ticket_uuids');
                $table->timestamp('issued_at');
                $table->timestamp('due_at');
                $table->timestamp('responded_at')->nullable();
                // pending | passed | failed | expired
                $table->string('status', 16)->default('pending')->index();
                $table->json('response_payload')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['distributor_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'distributor_spot_audits',
            'distribution_sales',
            'distributor_inventory_snapshots',
            'commission_models',
            'ticket_custody_ledger',
            'ticket_dispatch_items',
            'ticket_dispatches',
            'distributor_pairing_codes',
            'distributor_devices',
            'distributor_users',
            'distributors',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
