<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public storefront — checkout sessions, the held inventory inside
 * them, the waitlist, and a small set of extension columns on orders
 * to carry storefront-specific context (promo, UTM, IP, session FK).
 *
 * Why a session row and not just a cart cookie:
 *   - inventory is *held* against an open session, so an authoritative
 *     row is what the reservation service queries when computing
 *     remaining capacity.
 *   - expires_at lets a scheduled job release expired holds without
 *     waiting for the buyer to come back.
 *   - the session is also the audit trail for what was attempted vs.
 *     completed (analytics / fraud / abandoned-cart email).
 *
 * `organisation_id` is the platform-wide UUID (the same column shape
 * used by events, ad_campaigns, ticket_categories, …). Storing it
 * lets the dashboard run scoped queries without joining through
 * events on every read.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('checkout_sessions')) {
            Schema::create('checkout_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('organisation_id');
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();

                // open → paying → completed; cancelled / expired are terminal.
                // `paying` is the lock window between /pay and the gateway
                // webhook landing — protects against double-submit.
                $table->string('status', 20)->default('open');

                // Buyer envelope. Captured progressively during the flow,
                // so all nullable until /attendees has been called.
                $table->string('buyer_email', 191)->nullable();
                $table->string('buyer_name', 191)->nullable();
                $table->string('buyer_phone', 40)->nullable();
                $table->string('buyer_country_code', 2)->nullable();
                $table->string('buyer_locale', 10)->nullable();

                // Pricing snapshot — recomputed on every mutation by the
                // PriceCalculator and persisted here so the /pay step has
                // a single authoritative source.
                $table->string('currency', 6);
                $table->unsignedInteger('subtotal_cents')->default(0);
                $table->unsignedInteger('discount_cents')->default(0);
                $table->unsignedInteger('tax_cents')->default(0);
                $table->unsignedInteger('fee_cents')->default(0);
                $table->unsignedInteger('total_cents')->default(0);

                // Promo / discount snapshot. Keep the code as a string even
                // if the FK is later deleted so the order receipt is stable.
                $table->foreignId('promo_code_id')->nullable()
                    ->constrained('ticket_promo_codes')->nullOnDelete();
                $table->string('promo_code_snapshot', 64)->nullable();

                // Per-attendee data filled at the /attendees step. Shape:
                //   [{item_id: 1, attendees: [{name, email, phone, …}]}]
                $table->json('attendee_data')->nullable();

                // Marketing attribution. Forwarded from query string when
                // the session is created; doesn't drive any logic in core
                // but the reports surface reads it.
                $table->string('referral_source', 191)->nullable();
                $table->string('utm_source', 80)->nullable();
                $table->string('utm_medium', 80)->nullable();
                $table->string('utm_campaign', 80)->nullable();
                $table->string('utm_content', 191)->nullable();
                $table->string('utm_term', 191)->nullable();

                // Anti-bot fingerprinting. Hash the UA so we don't keep
                // PII while still being able to group sessions.
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent_hash', 64)->nullable();

                // Payment + fulfilment linkage. Filled by /pay and
                // OrderFulfillment respectively.
                $table->foreignId('payment_transaction_id')->nullable()
                    ->constrained('payment_transactions')->nullOnDelete();
                $table->foreignId('order_id')->nullable()
                    ->constrained('orders')->nullOnDelete();

                // Buyer-supplied idempotency key on session creation —
                // prevents accidental double-submits from re-creating the
                // session row when the network blips during checkout init.
                $table->string('idempotency_key', 80)->nullable();

                $table->timestamp('expires_at');
                $table->timestamp('locked_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();

                $table->timestamps();

                $table->index(['event_id', 'status']);
                $table->index(['organisation_id', 'status']);
                $table->index('expires_at');
                $table->unique(['idempotency_key']);

                $table->foreign('organisation_id')
                    ->references('uuid')->on('organizations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('checkout_session_items')) {
            Schema::create('checkout_session_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('checkout_session_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ticket_category_id')->constrained()->cascadeOnDelete();

                $table->unsignedInteger('quantity');
                $table->unsignedInteger('unit_price_cents');
                $table->string('currency', 6);
                $table->unsignedInteger('line_total_cents');

                // When the unit price was snapshotted. Used to invalidate
                // a session if the organizer changed the price mid-flow.
                $table->timestamp('price_locked_at');

                $table->timestamps();

                $table->index(['ticket_category_id']);
                // One line per (session, category) — multiple seats of the
                // same tier ride on `quantity`, not multiple rows.
                $table->unique(['checkout_session_id', 'ticket_category_id'], 'cs_items_unique_per_tier');
            });
        }

        if (! Schema::hasTable('waitlist_entries')) {
            Schema::create('waitlist_entries', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                // Null = waitlist for the whole event regardless of tier.
                $table->foreignId('ticket_category_id')->nullable()
                    ->constrained()->cascadeOnDelete();

                $table->string('email', 191);
                $table->string('name', 191)->nullable();
                $table->string('phone', 40)->nullable();
                $table->unsignedSmallInteger('quantity_requested')->default(1);

                // pending → notified (we DM'd them inventory is back) →
                // converted (they completed a purchase) | expired.
                $table->string('status', 20)->default('pending');
                $table->timestamp('notified_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->foreignId('converted_order_id')->nullable()
                    ->constrained('orders')->nullOnDelete();

                $table->timestamps();
                $table->index(['event_id', 'status']);
                $table->index(['email']);
            });
        }

        // ── orders extensions ──────────────────────────────────────────
        // Storefront-specific context that wasn't in the original orders
        // schema. All nullable so existing rows + non-storefront paths
        // (back-office comp tickets, admin-issued orders) keep working.
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'discount_cents')) {
                    $table->unsignedInteger('discount_cents')->default(0)->after('subtotal_cents');
                }
                if (! Schema::hasColumn('orders', 'promo_code_used')) {
                    $table->string('promo_code_used', 64)->nullable()->after('payment_reference');
                }
                if (! Schema::hasColumn('orders', 'checkout_session_id')) {
                    $table->foreignId('checkout_session_id')->nullable()
                        ->after('event_id')
                        ->constrained('checkout_sessions')->nullOnDelete();
                }
                if (! Schema::hasColumn('orders', 'ip_address')) {
                    $table->string('ip_address', 45)->nullable()->after('promo_code_used');
                }
                if (! Schema::hasColumn('orders', 'utm_source')) {
                    $table->string('utm_source', 80)->nullable()->after('ip_address');
                    $table->string('utm_medium', 80)->nullable()->after('utm_source');
                    $table->string('utm_campaign', 80)->nullable()->after('utm_medium');
                }
                if (! Schema::hasColumn('orders', 'buyer_country_code')) {
                    $table->string('buyer_country_code', 2)->nullable()->after('buyer_phone');
                }
                if (! Schema::hasColumn('orders', 'buyer_locale')) {
                    $table->string('buyer_locale', 10)->nullable()->after('buyer_country_code');
                }
                if (! Schema::hasColumn('orders', 'fulfilled_at')) {
                    $table->timestamp('fulfilled_at')->nullable()->after('placed_at');
                }
            });
        }

        // Per-item linkage to the issued offline ticket. Lets us look up
        // "which scannable ticket was issued for this line item" without
        // a fragile join through order + event + category.
        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('order_items', 'offline_ticket_id')) {
                    $table->foreignId('offline_ticket_id')->nullable()
                        ->after('ticket_category_id')
                        ->constrained('offline_tickets')->nullOnDelete();
                }
                if (! Schema::hasColumn('order_items', 'attendee_phone')) {
                    $table->string('attendee_phone', 40)->nullable()->after('attendee_email');
                }
                if (! Schema::hasColumn('order_items', 'qr_payload')) {
                    // Denormalised QR data so the confirmation email can
                    // render a per-line QR without a join to offline_tickets.
                    $table->text('qr_payload')->nullable()->after('offline_ticket_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
        Schema::dropIfExists('checkout_session_items');
        Schema::dropIfExists('checkout_sessions');

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['fulfilled_at', 'buyer_locale', 'buyer_country_code', 'utm_campaign', 'utm_medium', 'utm_source', 'ip_address', 'promo_code_used', 'discount_cents'] as $col) {
                    if (Schema::hasColumn('orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
                if (Schema::hasColumn('orders', 'checkout_session_id')) {
                    $table->dropConstrainedForeignId('checkout_session_id');
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                foreach (['qr_payload', 'attendee_phone'] as $col) {
                    if (Schema::hasColumn('order_items', $col)) {
                        $table->dropColumn($col);
                    }
                }
                if (Schema::hasColumn('order_items', 'offline_ticket_id')) {
                    $table->dropConstrainedForeignId('offline_ticket_id');
                }
            });
        }
    }
};
