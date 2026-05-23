<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sandbox tables — fully isolated from production payment tables so a
 * scan of `payment_*` never picks them up by accident. Per the design
 * spec (§15 #2): separate schema, separate retention.
 *
 * Tables:
 *   sandbox_merchants         test merchants, one per dev or per env
 *   sandbox_transactions      every simulated charge
 *   sandbox_payment_state_log audit trail of state transitions
 *   sandbox_refunds           partial + full refunds
 *   sandbox_disputes          manually-initiated chargebacks
 *   sandbox_webhook_outbox    pending + delivered webhook events
 *   sandbox_clocks            virtual clock state, per-merchant (§15 #3)
 *   sandbox_idempotency_keys  24h-ttl response cache
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sandbox_merchants')) {
            Schema::create('sandbox_merchants', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('slug', 80)->unique();
                $table->string('name', 120);

                // Per-developer vs per-env tenancy (§15 #1). user_id null
                // means a shared merchant for CI / shared staging use.
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

                $table->string('environment', 20)->default('test');     // test|staging
                $table->string('emulate_default', 32)->default('stripe');
                $table->string('default_currency', 6)->default('USD');

                $table->string('webhook_endpoint', 1000)->nullable();
                $table->string('webhook_signing_secret', 80)->nullable();
                $table->string('webhook_signing_secret_previous', 80)->nullable();
                $table->timestamp('webhook_secret_rotated_at')->nullable();

                $table->json('rules')->nullable();                       // {reject_avs_n, require_3ds_over, …}

                $table->boolean('is_private')->default(false);           // §15 #10
                $table->boolean('webhooks_parallel')->default(false);

                $table->timestamps();
                $table->index(['user_id', 'environment']);
            });
        }

        if (! Schema::hasTable('sandbox_clocks')) {
            Schema::create('sandbox_clocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sandbox_merchant_id')->constrained()->cascadeOnDelete();
                $table->timestamp('frozen_at')->nullable();              // wall time when first advanced
                $table->unsignedBigInteger('offset_seconds')->default(0); // total virtual offset
                $table->string('mode', 16)->default('real');             // real|virtual
                $table->timestamps();
                $table->unique('sandbox_merchant_id');
            });
        }

        if (! Schema::hasTable('sandbox_transactions')) {
            Schema::create('sandbox_transactions', function (Blueprint $table) {
                $table->id();
                $table->ulid('uuid')->unique();
                $table->foreignId('sandbox_merchant_id')->constrained()->cascadeOnDelete();

                $table->string('emulate', 32);                            // stripe|adyen|paynow|ecocash|…
                $table->string('method', 32)->default('card');            // card|ach|sepa|wallet|bnpl|mobile
                $table->string('state', 24)->index();                     // SandboxState enum value
                $table->string('reason_code', 60)->nullable();
                $table->string('scenario', 60)->default('success');

                $table->unsignedBigInteger('amount_minor');
                $table->unsignedBigInteger('amount_captured_minor')->default(0);
                $table->unsignedBigInteger('amount_refunded_minor')->default(0);
                $table->string('currency', 6);

                $table->string('reference', 80)->nullable()->index();    // caller idempotency key for charge body
                $table->string('idempotency_key', 80)->nullable();
                $table->string('acquirer_reference', 80)->nullable();    // synthetic, e.g. 'ach_…' 'sba_…'
                $table->string('provider_reference', 80)->nullable();    // mimics Stripe ch_…/pi_…
                $table->string('external_id', 80)->nullable()->index();  // caller's order id

                // Payment instrument snapshot — never store full PAN; the
                // magic-value lookup is enough for replay.
                $table->string('instrument_brand', 20)->nullable();
                $table->string('instrument_last4', 8)->nullable();
                $table->string('instrument_country', 2)->nullable();
                $table->json('instrument_meta')->nullable();             // bin, network, etc.

                $table->string('customer_email', 200)->nullable();
                $table->string('customer_msisdn', 32)->nullable();
                $table->string('customer_name', 120)->nullable();
                $table->string('customer_ip', 45)->nullable();

                $table->string('return_url', 1000)->nullable();
                $table->string('result_url', 1000)->nullable();
                $table->string('redirect_url', 1000)->nullable();

                $table->json('request_body')->nullable();
                $table->json('response_body')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamp('authorized_at')->nullable();
                $table->timestamp('captured_at')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('settlement_at')->nullable();
                $table->timestamp('expires_at')->nullable();             // auth-hold expiry (7d default)

                $table->timestamps();
                $table->index(['sandbox_merchant_id', 'state']);
                $table->index(['sandbox_merchant_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('sandbox_payment_state_log')) {
            Schema::create('sandbox_payment_state_log', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sandbox_transaction_id')->constrained()->cascadeOnDelete();
                $table->string('from_state', 24)->nullable();
                $table->string('to_state', 24);
                $table->string('reason', 120)->nullable();
                $table->string('actor', 24)->default('system');          // system|user|webhook|scenario|clock
                $table->json('context')->nullable();
                $table->timestamp('occurred_at');                        // wall time
                $table->timestamp('virtual_time_at');                    // sandbox-clock time
                $table->index(['sandbox_transaction_id', 'occurred_at']);
            });
        }

        if (! Schema::hasTable('sandbox_refunds')) {
            Schema::create('sandbox_refunds', function (Blueprint $table) {
                $table->id();
                $table->ulid('uuid')->unique();
                $table->foreignId('sandbox_transaction_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('amount_minor');
                $table->string('currency', 6);
                $table->string('state', 24);                              // pending|succeeded|failed
                $table->string('reason_code', 60)->nullable();
                $table->string('reason', 255)->nullable();
                $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('response_body')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sandbox_disputes')) {
            Schema::create('sandbox_disputes', function (Blueprint $table) {
                $table->id();
                $table->ulid('uuid')->unique();
                $table->foreignId('sandbox_transaction_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('amount_minor');
                $table->string('currency', 6);
                $table->string('status', 24)->default('open');           // open|under_review|won|lost
                $table->string('reason_code', 24);                       // 10.4 fraud, 13.1 product, etc.
                $table->json('evidence')->nullable();
                $table->timestamp('evidence_due_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sandbox_webhook_outbox')) {
            Schema::create('sandbox_webhook_outbox', function (Blueprint $table) {
                $table->id();
                $table->ulid('uuid')->unique();
                $table->string('event_id', 80)->unique();
                $table->foreignId('sandbox_merchant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sandbox_transaction_id')->nullable()->constrained()->cascadeOnDelete();

                $table->string('type', 80);                              // payment.captured, refund.failed, …
                $table->string('emulate', 32);                            // signing scheme + body shape
                $table->json('payload');
                $table->json('headers')->nullable();
                $table->string('signature', 256)->nullable();

                $table->timestamp('scheduled_for')->index();
                $table->timestamp('last_attempt_at')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->string('status', 16)->default('queued');         // queued|delivering|delivered|failed|dropped
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->text('response_body')->nullable();

                $table->timestamps();
                $table->index(['sandbox_merchant_id', 'status']);
                $table->index(['status', 'scheduled_for']);
            });
        }

        if (! Schema::hasTable('sandbox_idempotency_keys')) {
            Schema::create('sandbox_idempotency_keys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sandbox_merchant_id')->constrained()->cascadeOnDelete();
                $table->string('key', 120);
                $table->string('request_hash', 64);                       // sha256 of canonical request
                $table->unsignedSmallInteger('response_status');
                $table->json('response_body');
                $table->timestamp('expires_at');
                $table->timestamps();
                $table->unique(['sandbox_merchant_id', 'key']);
                $table->index('expires_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_idempotency_keys');
        Schema::dropIfExists('sandbox_webhook_outbox');
        Schema::dropIfExists('sandbox_disputes');
        Schema::dropIfExists('sandbox_refunds');
        Schema::dropIfExists('sandbox_payment_state_log');
        Schema::dropIfExists('sandbox_transactions');
        Schema::dropIfExists('sandbox_clocks');
        Schema::dropIfExists('sandbox_merchants');
    }
};
