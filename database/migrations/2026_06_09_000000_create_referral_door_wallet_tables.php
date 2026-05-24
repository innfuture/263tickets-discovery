<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema for the Tier-1 / Tier-2 roadmap additions:
 *
 *   affiliate_codes        — promo-code-like surface for affiliates
 *                            with commission attribution per order.
 *   affiliate_attributions — pivot Order ↔ ReferralCode, captures
 *                            commission cents at sale time.
 *   door_staff_shifts     — scheduled door-staff windows on an Event,
 *                            cross-references with door_pin to gate
 *                            scanner-app sign-in.
 *   wallet_pass_updates   — outbox for Apple/Google Wallet live
 *                            updates (door change, time shift, void).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliate_codes')) {
            Schema::create('affiliate_codes', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('organization_id')->index();
                $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
                $table->string('code', 64);
                $table->string('label', 191)->nullable();
                $table->string('affiliate_name', 191)->nullable();
                $table->string('affiliate_email', 191)->nullable();
                // basis points (250 = 2.5%) OR flat cents per ticket; one OR the other
                $table->unsignedSmallInteger('commission_bps')->nullable();
                $table->unsignedInteger('commission_flat_cents_per_ticket')->nullable();
                $table->string('currency', 3)->default('USD');
                $table->unsignedInteger('max_uses')->nullable();
                $table->unsignedInteger('uses_count')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['organization_id', 'code']);
                $table->index(['event_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('affiliate_attributions')) {
            Schema::create('affiliate_attributions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('affiliate_code_id')->constrained('affiliate_codes')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                // Captured AT sale time so future commission-rate
                // changes don't retroactively alter historical payouts.
                $table->unsignedInteger('commission_cents');
                $table->string('currency', 3);
                $table->string('settlement_status', 16)->default('pending')->index();
                // pending | accrued | paid | reversed
                $table->timestamp('attributed_at');
                $table->timestamp('settled_at')->nullable();
                $table->timestamps();

                $table->unique('order_id', 'affiliate_attr_order_uniq');
            });
        }

        if (! Schema::hasTable('door_staff_shifts')) {
            Schema::create('door_staff_shifts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()
                    ->constrained()->nullOnDelete();
                $table->string('staff_name', 191)->nullable();
                $table->string('door_label', 64)->nullable();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                // scheduled | active | completed | no_show | cancelled
                $table->string('status', 16)->default('scheduled')->index();
                $table->unsignedInteger('expected_scan_count')->nullable();
                $table->unsignedInteger('actual_scan_count')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'starts_at']);
                $table->index(['user_id', 'starts_at']);
            });
        }

        if (! Schema::hasTable('wallet_pass_updates')) {
            Schema::create('wallet_pass_updates', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('offline_ticket_id')->constrained()->cascadeOnDelete();
                $table->uuid('ticket_uuid');
                // door_change | time_shift | cancellation | voided | general
                $table->string('update_type', 24);
                $table->json('payload');
                // queued | dispatched_apple | dispatched_google | dispatched_both | failed
                $table->string('dispatch_status', 24)->default('queued')->index();
                $table->timestamp('queued_at');
                $table->timestamp('dispatched_at')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index('ticket_uuid');
                $table->index(['dispatch_status', 'queued_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_pass_updates');
        Schema::dropIfExists('door_staff_shifts');
        Schema::dropIfExists('affiliate_attributions');
        Schema::dropIfExists('affiliate_codes');
    }
};
