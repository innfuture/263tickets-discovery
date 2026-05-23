<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materialises every backing table the scaffolded settings + ops
 * pages need to become fully functional. One migration so the schema
 * lands atomically; each table is small and idempotent-guarded.
 *
 * Coverage:
 *   • Audit + compliance:  audit_events, data_exports, gdpr_requests
 *   • Developer surface:   api_tokens (personal + org), oauth_apps,
 *                          api_request_logs
 *   • Webhooks:            webhooks, webhook_deliveries
 *   • Billing:             subscriptions, payment_methods, invoices,
 *                          payouts, tax_rates, refund_policies
 *   • Orders:              orders, order_items
 *   • Marketing:           email_campaigns, social_posts,
 *                          integration_connections
 *
 * Plus JSON / scalar columns on `organizations` for low-cardinality
 * settings that don't deserve a table of their own:
 *   brand_kit, domain_settings, public_page_settings, email_identity,
 *   retention_settings, ticket_template_settings, date_format_settings,
 *   plan_tier.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Audit + Compliance ─────────────────────────────────────────
        if (! Schema::hasTable('audit_events')) {
            Schema::create('audit_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_type', 40)->default('user');      // user|system|api
                $table->string('action', 80);                            // organization.update, role.create, etc.
                $table->string('resource_type', 80)->nullable();
                $table->string('resource_id', 80)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->index();
                $table->index(['organization_id', 'action']);
                $table->index(['resource_type', 'resource_id']);
            });
        }

        if (! Schema::hasTable('data_exports')) {
            Schema::create('data_exports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->string('export_type', 40);    // events|attendees|tickets|financials|all
                $table->string('format', 10)->default('csv');
                $table->json('filters')->nullable();
                $table->string('status', 20)->default('pending'); // pending|processing|complete|failed
                $table->string('download_url', 500)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gdpr_requests')) {
            Schema::create('gdpr_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('subject_email');
                $table->string('subject_name')->nullable();
                $table->string('request_type', 20);    // access|erase|portability|rectify
                $table->string('status', 20)->default('open'); // open|in_progress|resolved|rejected
                $table->text('notes')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'status']);
            });
        }

        // ── Developer ──────────────────────────────────────────────────
        // Single api_tokens table covers BOTH personal access tokens
        // (organization_id = NULL, user-bound) and org API keys
        // (organization_id set, server-to-server). Differentiated via
        // `scope` and `organization_id` presence on read paths.
        if (! Schema::hasTable('api_tokens')) {
            Schema::create('api_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('token_hash', 64)->unique();   // sha256 of plain token
                $table->string('token_prefix', 12);            // last 4-8 chars shown after creation
                $table->json('abilities')->nullable();         // permission strings
                $table->json('ip_allowlist')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('oauth_apps')) {
            Schema::create('oauth_apps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('client_id', 64)->unique();
                $table->string('client_secret_hash', 64);
                $table->json('redirect_uris');
                $table->json('scopes');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('api_request_logs')) {
            Schema::create('api_request_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('api_token_id')->nullable()->constrained('api_tokens')->nullOnDelete();
                $table->string('method', 10);
                $table->string('path', 500);
                $table->unsignedSmallInteger('status_code');
                $table->unsignedInteger('latency_ms');
                $table->string('ip_address', 45)->nullable();
                $table->json('request_preview')->nullable();
                $table->json('response_preview')->nullable();
                $table->timestamp('created_at')->index();
                $table->index(['organization_id', 'created_at']);
            });
        }

        // ── Webhooks (shared by ops + dev pages) ───────────────────────
        if (! Schema::hasTable('webhooks')) {
            Schema::create('webhooks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('url', 2048);
                $table->string('signing_secret', 64);
                $table->json('subscribed_events');                 // ['ticket.sold', 'refund.processed', ...]
                $table->boolean('is_active')->default(true);
                $table->unsignedTinyInteger('retry_max')->default(5);
                $table->timestamp('last_delivery_at')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('webhook_deliveries')) {
            Schema::create('webhook_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
                $table->string('event', 80);
                $table->json('payload');
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->text('response_body')->nullable();
                $table->unsignedInteger('attempt')->default(1);
                $table->string('status', 20)->default('pending');  // pending|success|failed
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('created_at')->index();
                $table->index(['webhook_id', 'status']);
            });
        }

        // ── Billing ────────────────────────────────────────────────────
        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('plan_tier', 40)->default('free');  // free|starter|growth|enterprise
                $table->string('stripe_subscription_id')->nullable();
                $table->string('status', 20)->default('active');   // active|trialing|past_due|canceled
                $table->timestamp('current_period_start')->nullable();
                $table->timestamp('current_period_end')->nullable();
                $table->timestamp('canceled_at')->nullable();
                $table->json('limits')->nullable();                // { events: 10, tickets: 1000, ... }
                $table->timestamps();
                $table->unique('organization_id');
            });
        }

        if (! Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('stripe_payment_method_id')->nullable();
                $table->string('brand', 20);                       // visa|mc|amex
                $table->string('last4', 4);
                $table->unsignedSmallInteger('exp_month');
                $table->unsignedSmallInteger('exp_year');
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('invoice_number', 40)->unique();
                $table->string('stripe_invoice_id')->nullable();
                $table->date('issue_date');
                $table->date('due_date')->nullable();
                $table->unsignedInteger('amount_cents');
                $table->string('currency', 3);
                $table->string('status', 20)->default('open');     // open|paid|overdue|void
                $table->string('hosted_invoice_url', 500)->nullable();
                $table->string('pdf_url', 500)->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'issue_date']);
            });
        }

        if (! Schema::hasTable('payouts')) {
            Schema::create('payouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('stripe_payout_id')->nullable();
                $table->unsignedInteger('amount_cents');
                $table->string('currency', 3);
                $table->string('status', 20)->default('pending');  // pending|in_transit|paid|failed
                $table->string('arrival_date')->nullable();
                $table->string('bank_last4', 4)->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'status']);
            });
        }

        if (! Schema::hasTable('tax_rates')) {
            Schema::create('tax_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('name', 80);                        // e.g. "VAT", "GST"
                $table->string('region_code', 8);                  // ISO 3166-1 alpha-2 or special "*"
                $table->decimal('rate_percent', 5, 2);
                $table->boolean('inclusive')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['organization_id', 'region_code', 'name']);
            });
        }

        if (! Schema::hasTable('refund_policies')) {
            Schema::create('refund_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('name', 80);
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('cutoff_days')->nullable();   // null = no refund window
                $table->unsignedTinyInteger('restocking_fee_percent')->default(0);
                $table->boolean('pro_rated')->default(false);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->index(['organization_id']);
            });
        }

        // ── Orders ─────────────────────────────────────────────────────
        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('reference', 40)->unique();         // human-readable: ORD-AB12-3CD4
                $table->uuid('organisation_id');                   // matches the rest of the schema
                $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
                $table->string('buyer_name', 191);
                $table->string('buyer_email', 191);
                $table->string('buyer_phone', 40)->nullable();
                $table->string('status', 20)->default('pending');  // pending|paid|refunded|partially_refunded|voided
                $table->unsignedInteger('subtotal_cents');
                $table->unsignedInteger('tax_cents')->default(0);
                $table->unsignedInteger('fee_cents')->default(0);
                $table->unsignedInteger('total_cents');
                $table->string('currency', 3);
                $table->string('payment_method', 40)->nullable();  // card|cash|invoice|comp
                $table->string('payment_reference', 191)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('placed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['organisation_id', 'status']);
                $table->index(['buyer_email']);
                $table->foreign('organisation_id')->references('uuid')->on('organizations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ticket_category_id')->nullable()->constrained()->nullOnDelete();
                $table->string('attendee_name', 191);
                $table->string('attendee_email', 191)->nullable();
                $table->string('ticket_type', 80);                 // denormalised label
                $table->unsignedInteger('unit_price_cents');
                $table->string('currency', 3);
                $table->timestamp('checked_in_at')->nullable();
                $table->timestamps();
                $table->index(['order_id']);
            });
        }

        // ── Marketing ──────────────────────────────────────────────────
        if (! Schema::hasTable('email_campaigns')) {
            Schema::create('email_campaigns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->string('name', 191);
                $table->string('subject', 191);
                $table->text('body_html')->nullable();
                $table->string('audience_key', 40);                // all|past-attendees|opted-in
                $table->string('status', 20)->default('draft');    // draft|scheduled|sent|failed
                $table->unsignedInteger('recipient_count')->default(0);
                $table->unsignedInteger('opened_count')->default(0);
                $table->unsignedInteger('clicked_count')->default(0);
                $table->timestamp('scheduled_for')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'status']);
            });
        }

        if (! Schema::hasTable('social_posts')) {
            Schema::create('social_posts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
                $table->json('channels');                          // ['twitter', 'facebook', 'tiktok']
                $table->text('body');
                $table->json('media_paths')->nullable();
                $table->string('status', 20)->default('draft');    // draft|scheduled|published|failed
                $table->timestamp('scheduled_for')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->json('platform_response')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'status']);
            });
        }

        if (! Schema::hasTable('integration_connections')) {
            Schema::create('integration_connections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('connected_by')->constrained('users')->cascadeOnDelete();
                $table->string('provider', 40);                    // mailchimp|tiktok|instagram|linkedin|facebook|stripe|zoom|slack|google_calendar|google_analytics|meta_pixel|zapier|hubspot|salesforce
                $table->string('account_label', 191)->nullable();  // e.g. "@yourorg" or admin email
                $table->text('access_token')->nullable();          // encrypted in production
                $table->text('refresh_token')->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->json('scopes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('connected_at');
                $table->timestamps();
                $table->unique(['organization_id', 'provider']);
            });
        }

        // ── JSON / scalar settings on organizations ────────────────────
        // Low-cardinality knobs that don't need their own table.
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'brand_kit')) {
                $table->json('brand_kit')->nullable();
            }
            if (! Schema::hasColumn('organizations', 'domain_settings')) {
                $table->json('domain_settings')->nullable();
            }
            if (! Schema::hasColumn('organizations', 'public_page_settings')) {
                $table->json('public_page_settings')->nullable();
            }
            if (! Schema::hasColumn('organizations', 'email_identity')) {
                $table->json('email_identity')->nullable();
            }
            if (! Schema::hasColumn('organizations', 'retention_settings')) {
                $table->json('retention_settings')->nullable();
            }
            if (! Schema::hasColumn('organizations', 'ticket_template_settings')) {
                $table->json('ticket_template_settings')->nullable();
            }
            if (! Schema::hasColumn('organizations', 'date_format_settings')) {
                $table->json('date_format_settings')->nullable();
            }
        });

        // Personal-level date formats (per user) — small JSON column.
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'date_format_settings')) {
                $table->json('date_format_settings')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Drop user/org JSON cols first
        Schema::table('users', function (Blueprint $table) {
            foreach (['date_format_settings'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('organizations', function (Blueprint $table) {
            foreach ([
                'brand_kit', 'domain_settings', 'public_page_settings',
                'email_identity', 'retention_settings',
                'ticket_template_settings', 'date_format_settings',
            ] as $col) {
                if (Schema::hasColumn('organizations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Drop tables in reverse dependency order
        Schema::dropIfExists('integration_connections');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('email_campaigns');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('refund_policies');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('oauth_apps');
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('gdpr_requests');
        Schema::dropIfExists('data_exports');
        Schema::dropIfExists('audit_events');
    }
};
