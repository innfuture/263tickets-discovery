<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-2 storefront tables — reserved seating, group quotes, public
 * refund requests, and a small extension to event_page_views so the
 * public detail endpoint can record a view without a join.
 *
 * Seats: implements the model used by Ticketmaster / Eventbrite's
 * "reserved seating" tier. A SeatMap describes the venue. A Seat is
 * one bookable slot inside the map. A SeatHold is the per-checkout
 * lock — analogous to checkout_session_items but at seat granularity.
 *
 * Quote requests: corporate / group sales — buyer asks for N tickets,
 * organizer responds (out-of-band) and converts into a checkout
 * session later.
 *
 * Refund requests: public-facing refund initiation. The organizer's
 * back-office processes them against the existing PaymentRefund model.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seat_maps')) {
            Schema::create('seat_maps', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('organisation_id');
                $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name', 191);
                // SVG layout used by the renderer — coordinate space + zones.
                $table->json('layout')->nullable();
                $table->json('zones')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['event_id']);
                $table->foreign('organisation_id')->references('uuid')
                    ->on('organizations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('seats')) {
            Schema::create('seats', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('seat_map_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ticket_category_id')->nullable()
                    ->constrained()->nullOnDelete();
                $table->string('zone_code', 40)->nullable();
                $table->string('row_label', 8)->nullable();
                $table->string('seat_label', 8);
                $table->decimal('x', 8, 2)->nullable();
                $table->decimal('y', 8, 2)->nullable();
                // available | held | sold | blocked (organizer manually held)
                $table->string('status', 12)->default('available');
                $table->json('attributes')->nullable(); // accessible, restricted_view, etc.
                $table->timestamps();
                $table->unique(['seat_map_id', 'zone_code', 'row_label', 'seat_label'], 'seats_unique');
                $table->index(['ticket_category_id']);
                $table->index(['seat_map_id', 'status']);
            });
        }

        if (! Schema::hasTable('seat_holds')) {
            Schema::create('seat_holds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('seat_id')->constrained()->cascadeOnDelete();
                $table->foreignId('checkout_session_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_item_id')->nullable()
                    ->constrained('order_items')->nullOnDelete();
                $table->timestamp('expires_at');
                $table->timestamps();
                $table->unique('seat_id'); // one active hold per seat
                $table->index(['checkout_session_id']);
                $table->index('expires_at');
            });
        }

        if (! Schema::hasTable('quote_requests')) {
            Schema::create('quote_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->uuid('organisation_id');
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ticket_category_id')->nullable()
                    ->constrained()->nullOnDelete();
                $table->string('company_name', 191)->nullable();
                $table->string('contact_name', 191);
                $table->string('contact_email', 191);
                $table->string('contact_phone', 40)->nullable();
                $table->unsignedInteger('quantity_requested');
                $table->text('notes')->nullable();
                // pending → in_review → quoted → accepted | declined | expired
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('quoted_unit_price_cents')->nullable();
                $table->string('quoted_currency', 6)->nullable();
                $table->foreignId('converted_order_id')->nullable()
                    ->constrained('orders')->nullOnDelete();
                $table->timestamp('responded_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
                $table->index(['organisation_id', 'status']);
                $table->index(['event_id']);
                $table->foreign('organisation_id')->references('uuid')
                    ->on('organizations')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('refund_requests')) {
            Schema::create('refund_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->uuid('organisation_id');
                $table->string('reason_code', 40);
                $table->text('notes')->nullable();
                $table->string('contact_email', 191);
                // pending_review → approved | rejected → refunded (after gateway)
                $table->string('status', 20)->default('pending_review');
                $table->foreignId('payment_refund_id')->nullable()
                    ->constrained('payment_refunds')->nullOnDelete();
                $table->foreignId('reviewed_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->text('review_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
                $table->index(['organisation_id', 'status']);
                $table->index(['order_id']);
                $table->foreign('organisation_id')->references('uuid')
                    ->on('organizations')->cascadeOnDelete();
            });
        }

        // event_page_views already exposes session_id, referrer, utm_*
        // — no extension needed here.
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_requests');
        Schema::dropIfExists('quote_requests');
        Schema::dropIfExists('seat_holds');
        Schema::dropIfExists('seats');
        Schema::dropIfExists('seat_maps');
    }
};
