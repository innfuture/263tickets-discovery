<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-6 — four new subsystems in one shipping window:
 *
 *   1. NFC ticketing      Mobile-wallet tap as a verification path
 *                          alongside QR scanning. Apple VAS + Google
 *                          Smart Tap protocols.
 *   2. Buyer accounts     Authenticated buyer surface: dashboard,
 *                          favorites, notifications, self-service
 *                          ticket actions.
 *   3. Extension marketplace  Installable third-party apps with
 *                          signed manifests + scoped runtime permissions.
 *   4. Developer API      Public read API for third-party integrators
 *                          with Free/Basic/Enterprise/Premium tiers.
 *
 * Single migration so the cross-references (e.g. orders.buyer_id,
 * extension_installations.organization_id, developer_api_key tiers)
 * apply atomically per environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->createNfcTables();
        $this->createBuyerTables();
        $this->createExtensionTables();
        $this->createDeveloperApiTables();
        $this->extendExistingTables();
    }

    public function down(): void
    {
        foreach ([
            'developer_api_usage_daily', 'developer_api_keys',
            'developer_subscriptions', 'developer_accounts',
            'extension_audit_logs', 'extension_installations',
            'extension_versions', 'extensions', 'extension_developers',
            'buyer_notifications', 'buyer_favorites',
            'buyer_login_tokens', 'buyer_sessions', 'buyers',
            'nfc_verifications',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'buyer_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('buyer_id');
            });
        }
        if (Schema::hasTable('waitlist_entries') && Schema::hasColumn('waitlist_entries', 'buyer_id')) {
            Schema::table('waitlist_entries', function (Blueprint $table) {
                $table->dropConstrainedForeignId('buyer_id');
            });
        }
    }

    // ── 1. NFC ────────────────────────────────────────────────────────
    protected function createNfcTables(): void
    {
        if (! Schema::hasTable('nfc_verifications')) {
            Schema::create('nfc_verifications', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('offline_ticket_id')->nullable()
                    ->constrained()->nullOnDelete();
                // apple_vas | google_smart_tap | stub
                $table->string('provider', 32);
                // sha256 of the raw NFC payload — dedupe replay attempts
                $table->char('payload_hash', 64);
                $table->foreignId('scanner_device_id')->nullable()
                    ->constrained('scanner_devices')->nullOnDelete();
                $table->foreignId('scan_event_id')->nullable()
                    ->constrained('scan_events')->nullOnDelete();
                $table->string('verdict', 16);
                $table->string('reason_code', 64)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('verified_at');
                $table->timestamps();
                $table->unique('payload_hash');
                $table->index(['provider', 'verified_at']);
            });
        }
    }

    // ── 2. Buyer accounts ────────────────────────────────────────────
    protected function createBuyerTables(): void
    {
        if (! Schema::hasTable('buyers')) {
            Schema::create('buyers', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('email', 191)->unique();
                $table->string('name', 191)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('country_code', 2)->nullable();
                $table->string('locale', 10)->nullable();
                $table->boolean('marketing_opt_in')->default(false);
                $table->boolean('notifications_email')->default(true);
                $table->boolean('notifications_sms')->default(false);
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip', 45)->nullable();
                $table->json('preferences')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['email_verified_at']);
            });
        }

        if (! Schema::hasTable('buyer_login_tokens')) {
            Schema::create('buyer_login_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('buyer_id')->nullable()
                    ->constrained()->cascadeOnDelete();
                // Magic-link tokens are issued by email; we hash the
                // tail so a DB compromise doesn't leak live links.
                $table->string('email', 191);
                $table->char('token_hash', 64)->unique();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();
                $table->index(['email', 'consumed_at']);
            });
        }

        if (! Schema::hasTable('buyer_sessions')) {
            Schema::create('buyer_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('buyer_id')->constrained()->cascadeOnDelete();
                // sha256 of the bearer token issued at /verify. Stateful
                // sessions so we can revoke on logout / "sign out all".
                $table->char('token_hash', 64)->unique();
                $table->string('user_agent_hash', 64)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('last_active_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
                $table->index(['buyer_id', 'revoked_at']);
            });
        }

        if (! Schema::hasTable('buyer_favorites')) {
            Schema::create('buyer_favorites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('buyer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['buyer_id', 'event_id']);
            });
        }

        if (! Schema::hasTable('buyer_notifications')) {
            Schema::create('buyer_notifications', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('buyer_id')->constrained()->cascadeOnDelete();
                $table->string('type', 64);
                $table->string('title', 191);
                $table->text('body')->nullable();
                $table->json('data')->nullable();
                $table->string('action_url', 1000)->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['buyer_id', 'read_at']);
            });
        }
    }

    // ── 3. Extension marketplace ──────────────────────────────────────
    protected function createExtensionTables(): void
    {
        if (! Schema::hasTable('extension_developers')) {
            Schema::create('extension_developers', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('email', 191)->unique();
                $table->string('name', 191);
                $table->string('company', 191)->nullable();
                $table->string('website', 500)->nullable();
                // PEM-encoded RSA-2048 public key. Developers sign every
                // manifest with their matching private key; marketplace
                // verifies on submission.
                $table->text('public_key');
                $table->string('public_key_fingerprint', 128)->unique();
                $table->boolean('is_verified')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('extensions')) {
            Schema::create('extensions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('extension_developer_id')
                    ->constrained()->cascadeOnDelete();
                $table->string('slug', 80)->unique();
                $table->string('name', 191);
                $table->text('description')->nullable();
                $table->string('icon_url', 500)->nullable();
                // pending | approved | suspended | retired
                $table->string('status', 20)->default('pending');
                $table->string('category', 80)->nullable();
                $table->json('tags')->nullable();
                $table->string('homepage_url', 500)->nullable();
                $table->unsignedInteger('install_count')->default(0);
                $table->decimal('avg_rating', 3, 2)->nullable();
                $table->timestamp('first_approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['status', 'category']);
            });
        }

        if (! Schema::hasTable('extension_versions')) {
            Schema::create('extension_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('extension_id')->constrained()->cascadeOnDelete();
                // Semver — 1.4.2 etc. Unique per extension.
                $table->string('version', 32);
                // Full manifest as submitted — drives permissions,
                // webhooks, UI extension points, etc.
                $table->json('manifest');
                $table->json('declared_permissions');
                // Signature of the canonical manifest JSON, signed with
                // the developer's private key. Marketplace verifies
                // before this version becomes installable.
                $table->text('signature');
                // pending_review | approved | rejected | published
                $table->string('status', 20)->default('pending_review');
                $table->text('review_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->unique(['extension_id', 'version']);
                $table->index(['status']);
            });
        }

        if (! Schema::hasTable('extension_installations')) {
            Schema::create('extension_installations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('extension_id')->constrained()->cascadeOnDelete();
                $table->foreignId('extension_version_id')->constrained()->cascadeOnDelete();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('installed_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                // Per-install scoped API key the extension uses to call
                // back into our /api/v1/extensions/* surface.
                $table->char('api_key_hash', 64)->unique();
                $table->string('api_key_prefix', 8);
                // Permissions the org admin granted at install — usually
                // = manifest.declared_permissions, but may be a subset.
                $table->json('granted_permissions');
                $table->json('config')->nullable();
                // active | disabled | uninstalled
                $table->string('status', 20)->default('active');
                $table->timestamp('installed_at')->nullable();
                $table->timestamp('disabled_at')->nullable();
                $table->timestamp('uninstalled_at')->nullable();
                $table->timestamps();
                $table->unique(['organization_id', 'extension_id'], 'ext_install_unique');
                $table->index(['status']);
            });
        }

        if (! Schema::hasTable('extension_audit_logs')) {
            Schema::create('extension_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('extension_installation_id')
                    ->constrained()->cascadeOnDelete();
                $table->string('action', 80);
                $table->string('resource_type', 191)->nullable();
                $table->string('resource_id', 191)->nullable();
                $table->json('context')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['extension_installation_id', 'created_at']);
            });
        }
    }

    // ── 4. Developer API ──────────────────────────────────────────────
    protected function createDeveloperApiTables(): void
    {
        if (! Schema::hasTable('developer_accounts')) {
            Schema::create('developer_accounts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('email', 191)->unique();
                $table->string('name', 191);
                $table->string('company', 191)->nullable();
                $table->string('country_code', 2)->nullable();
                $table->string('website', 500)->nullable();
                // active | suspended | banned
                $table->string('status', 20)->default('active');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('developer_subscriptions')) {
            Schema::create('developer_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('developer_account_id')->constrained()->cascadeOnDelete();
                // free | basic | enterprise | premium
                $table->string('tier', 20)->default('free');
                $table->string('billing_status', 20)->default('active');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('renews_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['developer_account_id', 'tier']);
            });
        }

        if (! Schema::hasTable('developer_api_keys')) {
            Schema::create('developer_api_keys', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('developer_account_id')->constrained()->cascadeOnDelete();
                $table->string('label', 120);
                $table->char('key_hash', 64)->unique();
                $table->string('key_prefix', 12);
                // free | basic | enterprise | premium — pinned per-key
                // so a downgrade revokes elevated keys cleanly.
                $table->string('tier', 20)->default('free');
                // Read-only scopes for the public surface — keep narrow.
                // events.read | events.list | orders.aggregate.read |
                // analytics.read | webhooks.subscribe
                $table->json('scopes');
                $table->unsignedInteger('monthly_quota')->nullable();
                $table->unsignedInteger('quota_used_this_month')->default(0);
                $table->timestamp('quota_reset_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->string('last_used_ip', 45)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
                $table->index(['developer_account_id', 'revoked_at']);
            });
        }

        if (! Schema::hasTable('developer_api_usage_daily')) {
            Schema::create('developer_api_usage_daily', function (Blueprint $table) {
                $table->id();
                $table->foreignId('developer_api_key_id')->constrained()->cascadeOnDelete();
                $table->date('date');
                $table->unsignedInteger('request_count')->default(0);
                $table->unsignedInteger('success_count')->default(0);
                $table->unsignedInteger('error_count')->default(0);
                $table->unsignedInteger('rate_limited_count')->default(0);
                $table->timestamps();
                $table->unique(['developer_api_key_id', 'date'], 'dev_usage_unique');
            });
        }
    }

    // ── Cross-system extensions ───────────────────────────────────────
    protected function extendExistingTables(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'buyer_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('buyer_id')->nullable()->after('event_id')
                    ->constrained()->nullOnDelete();
                $table->index(['buyer_id', 'status']);
            });
        }
        if (Schema::hasTable('waitlist_entries') && ! Schema::hasColumn('waitlist_entries', 'buyer_id')) {
            Schema::table('waitlist_entries', function (Blueprint $table) {
                $table->foreignId('buyer_id')->nullable()->after('event_id')
                    ->constrained()->nullOnDelete();
            });
        }
    }
};
