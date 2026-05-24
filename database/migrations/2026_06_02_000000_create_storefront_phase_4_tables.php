<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-4 storefront tables. Cohesive backend feature set rolled into
 * one migration window:
 *
 *   event_bundles / bundle_events       Season passes — one purchase issues
 *                                        tickets across N events.
 *   ticket_transfers                    Buyer-to-buyer transfer / resale.
 *   referral_codes / referral_credits   Buyer shares a code; referrer is
 *                                        credited a gift card on each
 *                                        successful order from a referee.
 *   memberships / membership_periods    Annual memberships with renewal
 *                                        notifications + benefit tracking.
 *   event_templates                     Recurring events (weekly comedy
 *                                        night, monthly meetup); a cron
 *                                        spawns the next instance.
 *   quantity_discount_rules             "Buy 4, save 10%" mechanics.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Bundles / season passes ──────────────────────────────────
        if (! Schema::hasTable('event_bundles')) {
            Schema::create('event_bundles', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('organisation_id');
                $table->string('name', 191);
                $table->text('description')->nullable();
                $table->string('slug', 200)->unique();
                $table->unsignedInteger('price_cents');
                $table->string('currency', 6);
                // "Save vs buying separately" line shown in the UI.
                $table->unsignedInteger('savings_cents')->nullable();
                $table->unsignedInteger('total_capacity')->nullable();
                $table->unsignedInteger('sold_count')->default(0);
                $table->timestamp('sales_start_at')->nullable();
                $table->timestamp('sales_end_at')->nullable();
                $table->boolean('is_visible')->default(true);
                $table->string('image_path', 500)->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['organisation_id', 'is_visible']);
                $table->foreign('organisation_id')->references('uuid')
                    ->on('organizations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('bundle_events')) {
            Schema::create('bundle_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_bundle_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                // Which tier in the included event a bundle holder gets —
                // nullable means "any tier of organizer's choosing".
                $table->foreignId('ticket_category_id')->nullable()
                    ->constrained()->nullOnDelete();
                $table->unsignedSmallInteger('quantity')->default(1);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['event_bundle_id', 'event_id'], 'bundle_events_unique');
            });
        }

        // ── Ticket transfers / resale ────────────────────────────────
        if (! Schema::hasTable('ticket_transfers')) {
            Schema::create('ticket_transfers', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('offline_ticket_id')->constrained()->cascadeOnDelete();
                $table->foreignId('source_order_item_id')->constrained('order_items')->cascadeOnDelete();
                $table->foreignId('new_order_item_id')->nullable()
                    ->constrained('order_items')->nullOnDelete();
                // From / to identity — sender is implicitly the original
                // buyer; recipient is captured by email + name.
                $table->string('from_email', 191);
                $table->string('to_email', 191);
                $table->string('to_name', 191)->nullable();
                $table->text('message')->nullable();
                // offered → claimed → completed | declined | expired | revoked
                $table->string('status', 20)->default('offered');
                // Single-use claim code mailed to the recipient.
                $table->string('claim_token', 64)->unique();
                // Resale: optional sale price the original buyer is
                // charging. null = free transfer / gift.
                $table->unsignedInteger('sale_price_cents')->nullable();
                $table->string('currency', 6)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('claimed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['to_email', 'status']);
                $table->index(['from_email']);
            });
        }

        // ── Referral codes + credit ledger ───────────────────────────
        if (! Schema::hasTable('referral_codes')) {
            Schema::create('referral_codes', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('organisation_id');
                $table->string('code', 32)->unique();
                $table->string('owner_email', 191);
                $table->string('owner_name', 191)->nullable();
                // Reward shape: flat gift-card amount per referred order.
                // Future: bps share or tiered ladders — keep this row
                // small for v1.
                $table->unsignedInteger('reward_cents');
                $table->string('reward_currency', 6);
                // Optional cap to stop a single referrer earning unbounded.
                $table->unsignedInteger('max_rewards')->nullable();
                $table->unsignedInteger('rewards_count')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['organisation_id', 'is_active']);
                $table->foreign('organisation_id')->references('uuid')
                    ->on('organizations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('referral_credits')) {
            Schema::create('referral_credits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('referral_code_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('gift_card_id')->nullable()
                    ->constrained()->nullOnDelete();
                $table->unsignedInteger('amount_cents');
                $table->string('currency', 6);
                $table->timestamps();
                $table->unique(['referral_code_id', 'order_id'], 'referral_credit_unique');
            });
        }

        // ── Memberships + renewal periods ────────────────────────────
        if (! Schema::hasTable('memberships')) {
            Schema::create('memberships', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('organisation_id');
                $table->string('plan_name', 120);
                $table->text('plan_description')->nullable();
                $table->unsignedInteger('price_cents');
                $table->string('currency', 6);
                $table->unsignedSmallInteger('period_months')->default(12);
                // Optional benefit description rendered on the member's
                // dashboard. Free text — `discount_percent` / event
                // access lists go in `benefits` JSON.
                $table->json('benefits')->nullable();
                $table->string('member_email', 191);
                $table->string('member_name', 191);
                // active → expired (period passed, not renewed) → cancelled
                $table->string('status', 20)->default('active');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('renewed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->boolean('auto_renew')->default(false);
                $table->timestamps();
                $table->index(['organisation_id', 'status']);
                $table->index(['member_email']);
                $table->foreign('organisation_id')->references('uuid')
                    ->on('organizations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('membership_periods')) {
            Schema::create('membership_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()
                    ->constrained()->nullOnDelete();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedInteger('paid_cents');
                $table->string('currency', 6);
                $table->timestamps();
                $table->index(['membership_id', 'ends_at']);
            });
        }

        // ── Event templates / recurring events ───────────────────────
        if (! Schema::hasTable('event_templates')) {
            Schema::create('event_templates', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('organisation_id');
                $table->string('name', 191);
                // The Event row to clone — name, venue, lineup, tier
                // structure all copied. Inventory does NOT clone.
                $table->foreignId('source_event_id')->constrained('events')->cascadeOnDelete();
                // weekly | daily | monthly | nth_weekday_of_month
                $table->string('cadence', 32);
                $table->json('cadence_meta')->nullable(); // day-of-week, time, etc.
                // Stop emitting after this date / count.
                $table->timestamp('repeat_until')->nullable();
                $table->unsignedInteger('max_instances')->nullable();
                $table->unsignedInteger('instances_created')->default(0);
                $table->timestamp('last_generated_at')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['organisation_id', 'is_active']);
                $table->index(['next_run_at']);
                $table->foreign('organisation_id')->references('uuid')
                    ->on('organizations')->cascadeOnDelete();
            });
        }

        // ── Quantity discount rules ──────────────────────────────────
        if (! Schema::hasTable('quantity_discount_rules')) {
            Schema::create('quantity_discount_rules', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('organisation_id');
                $table->string('name', 120);
                // Scope: a tier (ticket_category_id) or whole-event.
                $table->foreignId('ticket_category_id')->nullable()
                    ->constrained()->cascadeOnDelete();
                $table->foreignId('event_id')->nullable()
                    ->constrained()->cascadeOnDelete();
                // Trigger: buy this many at the qualifying tier.
                $table->unsignedInteger('min_quantity');
                // Reward shape — exactly one of these is non-null.
                $table->unsignedInteger('discount_percent')->nullable();
                $table->unsignedInteger('discount_fixed_cents')->nullable();
                // "Buy N, get M free" — uses both fields.
                $table->unsignedInteger('free_quantity')->nullable();
                $table->timestamp('valid_from')->nullable();
                $table->timestamp('valid_until')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['organisation_id', 'is_active']);
                $table->foreign('organisation_id')->references('uuid')
                    ->on('organizations')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'quantity_discount_rules', 'event_templates',
            'membership_periods', 'memberships',
            'referral_credits', 'referral_codes',
            'ticket_transfers', 'bundle_events', 'event_bundles',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
