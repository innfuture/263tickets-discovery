<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backing tables for the full settings build-out — covers every page
 * that was previously stubbed by ScaffoldController.
 *
 * Two flavours of storage land here:
 *
 *   1. organization_settings — a single JSON key-value store per org
 *      that backs the small-shape settings (brand kit, domain config,
 *      public profile toggles, ticket-template choices, email sender
 *      identity, taxes, retention windows, date/number formats…).
 *      One row per org, JSON column merged at the application layer.
 *
 *   2. Dedicated tables for collection features: webhooks, api keys,
 *      api tokens, audit logs, exports, gdpr requests, sessions,
 *      payment methods, invoices, payouts, refund policies, oauth
 *      apps, integration connections, api request logs, webhook
 *      deliveries.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'trusted_ip_allowlist')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('trusted_ip_allowlist')->nullable();
            });
        }

        if (! Schema::hasTable('organization_settings')) {
            Schema::create('organization_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
                $table->json('data')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('organization_webhooks')) {
            Schema::create('organization_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('url');
            $table->json('events');
            $table->string('signing_secret', 64);
            $table->boolean('is_active')->default(true);
            $table->string('channel', 16)->default('operations');
            $table->unsignedSmallInteger('retry_limit')->default(5);
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamps();

                $table->index(['organization_id', 'channel']);
            });
        }

        Schema::create('organization_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('payload');
            $table->text('response_body')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['organization_webhook_id', 'created_at'], 'owhd_owid_created_idx');
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->constrained('users');
            $table->string('name');
            $table->string('prefix', 12)->unique();
            $table->string('token_hash', 64);
            $table->json('scopes')->nullable();
            $table->json('ip_allowlist')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('personal_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('prefix', 12)->unique();
            $table->string('token_hash', 64);
            $table->json('scopes')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('device_label')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
        });

        Schema::create('user_two_factor_secrets', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('secret', 64);
            $table->json('recovery_codes');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_type')->default('user'); // user|api|system
            $table->string('action');
            $table->string('resource_type')->nullable();
            $table->string('resource_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
        });

        Schema::create('data_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_id')->constrained('users');
            $table->string('type'); // events|attendees|tickets|financials|all
            $table->string('format', 10); // csv|json
            $table->string('delivery'); // email|s3
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('status'); // queued|processing|ready|failed
            $table->string('download_path')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gdpr_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('requester_email');
            $table->string('type'); // access|erase
            $table->string('status')->default('open'); // open|in_review|resolved|rejected
            $table->text('notes')->nullable();
            $table->foreignId('resolver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('brand', 32);
            $table->string('last4', 4);
            $table->unsignedSmallInteger('exp_month');
            $table->unsignedSmallInteger('exp_year');
            $table->string('holder_name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('provider_id')->nullable(); // stripe pm_xxx — opaque
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('status'); // draft|due|paid|overdue|void
            $table->json('line_items');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('status'); // pending|paid|failed|cancelled
            $table->string('destination_label')->nullable();
            $table->date('arrival_on')->nullable();
            $table->string('external_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('refund_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('cutoff_hours')->default(48);
            $table->boolean('prorated')->default(false);
            $table->unsignedSmallInteger('restocking_fee_percent')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('oauth_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->constrained('users');
            $table->string('name');
            $table->string('client_id', 64)->unique();
            $table->string('client_secret_hash', 64);
            $table->json('redirect_uris');
            $table->json('scopes');
            $table->string('homepage_url')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connected_by_id')->constrained('users');
            $table->string('provider'); // stripe, mailchimp, zapier, slack, zoom, google_calendar, ga4, meta_pixel, hubspot, salesforce
            $table->string('account_label')->nullable();
            $table->json('scopes')->nullable();
            $table->json('config')->nullable();
            $table->timestamp('connected_at')->useCurrent();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'provider']);
        });

        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('endpoint');
            $table->string('method', 8);
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('duration_ms');
            $table->string('ip_address', 45)->nullable();
            $table->json('request_preview')->nullable();
            $table->json('response_preview')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('integration_connections');
        Schema::dropIfExists('oauth_apps');
        Schema::dropIfExists('refund_policies');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('gdpr_requests');
        Schema::dropIfExists('data_exports');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_two_factor_secrets');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('personal_api_tokens');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('organization_webhook_deliveries');
        Schema::dropIfExists('organization_webhooks');
        Schema::dropIfExists('organization_settings');

        if (Schema::hasColumn('users', 'trusted_ip_allowlist')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('trusted_ip_allowlist');
            });
        }
    }
};
