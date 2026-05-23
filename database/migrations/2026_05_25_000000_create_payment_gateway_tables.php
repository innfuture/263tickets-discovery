<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provider-agnostic payment tables. Distinct from the existing
 * `payment_methods` table (cards on file for org subscriptions) — these
 * track payer-side transactions through one of the Zimbabwean gateways
 * (EcoCash, Paynow, Pesepay, Zimswitch).
 *
 *   payment_transactions    one row per charge attempt
 *   payment_refunds         child rows for partial or full refunds
 *   payment_webhook_events  every parsed inbound webhook (idempotency)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_transactions')) {
            Schema::create('payment_transactions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

                // Polymorphic owner — an order, ticket, subscription, anything.
                $table->nullableMorphs('payable');

                $table->string('gateway', 32);                            // ecocash|paynow|pesepay|zimswitch
                $table->string('reference', 80)->unique();                // our idempotency key
                $table->string('gateway_reference', 200)->nullable()->index();
                $table->string('status', 30)->index();                    // PaymentStatus enum value
                $table->unsignedBigInteger('amount_minor');
                $table->string('currency', 6);
                $table->string('description', 255)->nullable();

                $table->string('customer_email', 200)->nullable();
                $table->string('customer_msisdn', 32)->nullable();
                $table->string('customer_name', 120)->nullable();
                $table->string('customer_ip', 45)->nullable();

                $table->string('return_url', 1000)->nullable();
                $table->string('result_url', 1000)->nullable();
                $table->string('redirect_url', 1000)->nullable();
                $table->string('poll_url', 1000)->nullable();
                $table->text('instructions')->nullable();

                $table->json('metadata')->nullable();
                $table->json('last_response')->nullable();
                $table->unsignedSmallInteger('reconcile_attempts')->default(0);
                $table->timestamp('last_reconciled_at')->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->timestamp('failed_at')->nullable();

                $table->timestamps();
                $table->index(['gateway', 'status']);
                $table->index(['organization_id', 'status']);
                $table->index(['status', 'created_at']);
            });
        }

        if (! Schema::hasTable('payment_refunds')) {
            Schema::create('payment_refunds', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('payment_transaction_id')->constrained()->cascadeOnDelete();
                $table->string('reference', 80)->unique();
                $table->string('gateway_reference', 200)->nullable()->index();
                $table->string('status', 30)->index();
                $table->unsignedBigInteger('amount_minor');
                $table->string('currency', 6);
                $table->string('reason', 255)->nullable();
                $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->json('last_response')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_webhook_events')) {
            Schema::create('payment_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('gateway', 32)->index();
                $table->string('event_type', 80);
                $table->string('reference', 80)->nullable()->index();
                $table->string('gateway_reference', 200)->nullable()->index();
                $table->string('status', 30);
                $table->string('signature_hash', 80)->nullable()->index();   // payload fingerprint for dedupe
                $table->json('payload');
                $table->json('headers')->nullable();
                $table->string('processing_status', 20)->default('received'); // received|processed|skipped|failed
                $table->text('processing_error')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                $table->index(['gateway', 'processing_status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_transactions');
    }
};
