<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Third-party scanner integration schema.
 *
 *   scanner_profiles      one per logical "gate" or "scanning role"
 *                         (e.g. "Main entrance — Saturday"); carries
 *                         capabilities, event allowlist, IP allowlist,
 *                         outbound webhook config.
 *   scanner_devices       one per paired physical device. Token is
 *                         stored sha256 — only the prefix + a one-time
 *                         display value are recoverable.
 *   scanner_pairing_codes 30-minute one-time codes a mobile app
 *                         exchanges for a long-lived device token.
 *   scan_events           append-only log of every scan attempt with
 *                         the fraud verdict, geo, and device telemetry.
 *                         offline_tickets.scan_count is the cheap
 *                         counter; this table is the audit trail.
 *
 * Org scoping uses the UUID convention the rest of the platform schema
 * settled on (organisation_id ↔ organizations.uuid).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scanner_profiles')) {
            Schema::create('scanner_profiles', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('organisation_id');
                $table->foreign('organisation_id')->references('uuid')->on('teams')->cascadeOnDelete();

                $table->string('name', 120);
                $table->string('description', 500)->nullable();
                $table->string('status', 20)->default('active');  // active|disabled

                // Capabilities — JSON list, drawn from a known set
                // (scan, verify, revoke, view_analytics). Mobile app
                // reads /me to discover what it's allowed to do.
                $table->json('capabilities');

                // Event scoping — JSON list of event ids the profile
                // can scan. Null/empty = all events in the org.
                $table->json('allowed_event_ids')->nullable();

                // IP allowlist — CIDR strings. Null/empty = any IP.
                $table->json('ip_allowlist')->nullable();

                // Rate limit + fraud thresholds. Defaults are sane;
                // organizer can tighten per profile.
                $table->unsignedSmallInteger('max_scans_per_minute')->default(60);
                $table->unsignedSmallInteger('duplicate_window_seconds')->default(10);

                // Outbound webhook for real-time scan delivery. Each
                // event POSTs to this URL with an HMAC-SHA256 signature
                // in `X-Scanner-Signature`.
                $table->string('webhook_url', 1000)->nullable();
                $table->string('webhook_secret', 80)->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['organisation_id', 'status']);
            });
        }

        if (! Schema::hasTable('scanner_devices')) {
            Schema::create('scanner_devices', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('scanner_profile_id')->constrained()->cascadeOnDelete();

                // Token storage — sha256 the secret, keep only the
                // prefix in cleartext for display ("scn_…ab12").
                $table->string('token_hash', 64)->unique();
                $table->string('token_prefix', 16);

                $table->string('device_label', 120);
                $table->string('platform', 20)->default('other'); // ios|android|web|other
                $table->string('app_version', 40)->nullable();
                $table->string('hardware_id', 120)->nullable();   // opaque, app-supplied

                $table->timestamp('last_seen_at')->nullable();
                $table->string('last_known_ip', 45)->nullable();
                $table->decimal('last_known_lat', 10, 7)->nullable();
                $table->decimal('last_known_lng', 10, 7)->nullable();

                $table->string('status', 20)->default('active'); // active|disabled|revoked
                $table->timestamp('revoked_at')->nullable();
                $table->string('revoked_reason', 255)->nullable();

                $table->timestamps();
                $table->index(['scanner_profile_id', 'status']);
            });
        }

        if (! Schema::hasTable('scanner_pairing_codes')) {
            Schema::create('scanner_pairing_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('scanner_profile_id')->constrained()->cascadeOnDelete();
                $table->string('code', 12)->unique();
                $table->string('hint_label', 120)->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->foreignId('used_by_device_id')->nullable()->constrained('scanner_devices')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index('expires_at');
            });
        }

        if (! Schema::hasTable('scan_events')) {
            Schema::create('scan_events', function (Blueprint $table) {
                $table->id();
                $table->ulid('uuid')->unique();
                $table->foreignId('scanner_device_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('scanner_profile_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('offline_ticket_id')->nullable()->constrained()->nullOnDelete();

                $table->string('payload', 255);                  // raw QR / ticket number
                $table->string('verdict', 12);                   // allow|warn|deny
                $table->string('reason_code', 60)->nullable();
                $table->json('fraud_flags')->nullable();         // [{rule, severity, message}, ...]

                $table->boolean('was_duplicate')->default(false);
                $table->boolean('was_voided')->default(false);
                $table->boolean('was_admitted')->default(false);

                $table->decimal('client_lat', 10, 7)->nullable();
                $table->decimal('client_lng', 10, 7)->nullable();
                $table->string('client_ip', 45)->nullable();
                $table->timestamp('client_at')->nullable();      // scanner's local time
                $table->unsignedInteger('latency_ms')->nullable();
                $table->json('device_meta')->nullable();         // battery, signal, etc.

                $table->timestamp('created_at')->useCurrent();   // server time
                $table->index(['event_id', 'created_at']);
                $table->index(['scanner_device_id', 'created_at']);
                $table->index(['verdict', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_events');
        Schema::dropIfExists('scanner_pairing_codes');
        Schema::dropIfExists('scanner_devices');
        Schema::dropIfExists('scanner_profiles');
    }
};
