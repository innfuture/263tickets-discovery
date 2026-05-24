<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-3 storefront tables — automation tokens (for n8n / Zapier /
 * any HTTP automation tool), gift cards / store credit, event add-ons
 * (parking, merch, swag bags), organization custom domains, and a
 * per-event refund-policy column.
 *
 * Why these here instead of further sharding migration files:
 *   - All share a single shipping window
 *   - All cross-reference each other (gift cards funded by add-on
 *     purchase; automation tokens scope what an n8n flow can do with
 *     orders / gift cards / addons)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── n8n / automation surface ─────────────────────────────────
        if (! Schema::hasTable('automation_tokens')) {
            Schema::create('automation_tokens', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->string('label', 120);
                // 4-char display prefix shown in the dashboard so users
                // can ID a token without revealing the secret. Mirrors
                // the scanner-token UX (`scn_…`).
                $table->string('display_prefix', 8);
                // sha256 of the secret — never store the raw secret.
                $table->char('secret_hash', 64);
                // Scopes: read, orders.write, refunds.write, messages.write,
                // events.write, gift_cards.write, addons.write
                $table->json('scopes');
                $table->timestamp('last_used_at')->nullable();
                $table->string('last_used_ip', 45)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
                $table->index(['organization_id', 'revoked_at']);
                $table->unique('secret_hash');
            });
        }

        // ── Gift cards / store credit ────────────────────────────────
        if (! Schema::hasTable('gift_cards')) {
            Schema::create('gift_cards', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('organisation_id');
                // Human-readable code (caps + digits, no I/O/0/1) the
                // buyer types in. Unique across the platform.
                $table->string('code', 32)->unique();
                $table->unsignedInteger('initial_balance_cents');
                $table->unsignedInteger('balance_cents');
                $table->string('currency', 6);
                // Optional buyer/recipient metadata for "buy a gift card
                // and email it on date X" UX.
                $table->string('purchaser_email', 191)->nullable();
                $table->string('recipient_email', 191)->nullable();
                $table->string('recipient_name', 191)->nullable();
                $table->text('message')->nullable();
                $table->string('source', 24)->default('purchase'); // purchase|comp|promo|refund_credit
                $table->timestamp('valid_from')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('redeemed_at')->nullable(); // first redemption
                $table->timestamps();
                $table->softDeletes();
                $table->index(['organisation_id', 'balance_cents']);
                $table->foreign('organisation_id')->references('uuid')
                    ->on('organizations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('gift_card_redemptions')) {
            Schema::create('gift_card_redemptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gift_card_id')->constrained()->cascadeOnDelete();
                $table->foreignId('checkout_session_id')->nullable()
                    ->constrained()->nullOnDelete();
                $table->foreignId('order_id')->nullable()
                    ->constrained()->nullOnDelete();
                // Positive for redemption, negative for void/reversal.
                $table->integer('amount_cents');
                $table->string('currency', 6);
                $table->string('action', 20)->default('redeemed'); // redeemed|voided
                $table->timestamps();
                $table->index(['gift_card_id', 'action']);
            });
        }

        // ── Add-ons (parking, merch, donation, etc.) ─────────────────
        if (! Schema::hasTable('event_addons')) {
            Schema::create('event_addons', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->uuid('organisation_id');
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->unsignedInteger('price_cents');
                $table->string('currency', 6);
                // Stock can be null = unlimited (digital), 0 = sold out,
                // N = remaining count.
                $table->unsignedInteger('stock')->nullable();
                $table->unsignedInteger('sold_count')->default(0);
                $table->unsignedSmallInteger('min_per_order')->default(0);
                $table->unsignedSmallInteger('max_per_order')->default(10);
                // Whether an addon requires at least one ticket in the
                // cart (e.g. parking pass) vs being standalone-buyable
                // (e.g. donation).
                $table->boolean('requires_ticket')->default(true);
                $table->boolean('is_visible')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('image_path', 500)->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['event_id', 'is_visible']);
                $table->foreign('organisation_id')->references('uuid')
                    ->on('organizations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('checkout_session_addons')) {
            Schema::create('checkout_session_addons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('checkout_session_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_addon_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('quantity');
                $table->unsignedInteger('unit_price_cents');
                $table->string('currency', 6);
                $table->unsignedInteger('line_total_cents');
                $table->timestamps();
                $table->unique(['checkout_session_id', 'event_addon_id'], 'cs_addon_unique');
            });
        }

        // ── Custom domains per organization ──────────────────────────
        if (! Schema::hasTable('organization_domains')) {
            Schema::create('organization_domains', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('hostname', 253)->unique();
                // DNS verification token the operator adds as a TXT
                // record before we route requests to them.
                $table->string('verification_token', 64);
                $table->boolean('verified')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
                $table->index(['organization_id', 'verified']);
            });
        }

        // ── Event-level refund policy ────────────────────────────────
        if (Schema::hasTable('events') && ! Schema::hasColumn('events', 'refund_policy_rules')) {
            Schema::table('events', function (Blueprint $table) {
                // JSON: { "rules": [{"hours_before": 168, "percent": 100},
                //                   {"hours_before": 24, "percent": 50}],
                //         "default_percent": 0,
                //         "non_refundable_fees": true }
                $table->json('refund_policy_rules')->nullable()->after('refund_policy');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_domains');
        Schema::dropIfExists('checkout_session_addons');
        Schema::dropIfExists('event_addons');
        Schema::dropIfExists('gift_card_redemptions');
        Schema::dropIfExists('gift_cards');
        Schema::dropIfExists('automation_tokens');

        if (Schema::hasTable('events') && Schema::hasColumn('events', 'refund_policy_rules')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('refund_policy_rules');
            });
        }
    }
};
