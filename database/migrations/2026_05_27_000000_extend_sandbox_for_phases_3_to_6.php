<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema additions for sandbox spec phases 4-6:
 *
 *   sandbox_balance_ledger    per-merchant credit/debit log; powers the
 *                             dashboard "available vs pending" view (§6,
 *                             phase 4).
 *   sandbox_payment_methods   saved card / bank instruments for
 *                             card-on-file (off-session) flows (§5.1,
 *                             phase 5).
 *   sandbox_recordings        captured production responses for the
 *                             Diff-vs-prod tab and replay (§4.3,
 *                             phase 6).
 *
 * Three small tables; no destructive changes to phase-1 schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sandbox_balance_ledger')) {
            Schema::create('sandbox_balance_ledger', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sandbox_merchant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sandbox_transaction_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('sandbox_refund_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('sandbox_dispute_id')->nullable()->constrained()->cascadeOnDelete();

                $table->string('direction', 6);                       // credit|debit
                $table->unsignedBigInteger('amount_minor');
                $table->string('currency', 6);
                $table->string('category', 24);                       // capture|refund|chargeback|fee|payout
                $table->text('memo')->nullable();

                // T+N settlement gate. `available_at` < now() means the
                // amount counts toward "available"; otherwise "pending".
                $table->timestamp('available_at')->nullable();
                $table->timestamps();

                $table->index(['sandbox_merchant_id', 'available_at']);
                $table->index(['sandbox_merchant_id', 'category']);
            });
        }

        if (! Schema::hasTable('sandbox_payment_methods')) {
            Schema::create('sandbox_payment_methods', function (Blueprint $table) {
                $table->id();
                $table->ulid('uuid')->unique();
                $table->foreignId('sandbox_merchant_id')->constrained()->cascadeOnDelete();

                // Stable token the host app sends back on subsequent
                // charges to use the saved method (COF / off-session).
                $table->string('token', 64)->unique();

                $table->string('kind', 24);                           // card|bank|wallet
                $table->string('brand', 20)->nullable();              // visa|mastercard|amex|apple_pay|google_pay
                $table->string('last4', 8)->nullable();
                $table->unsignedSmallInteger('exp_month')->nullable();
                $table->unsignedSmallInteger('exp_year')->nullable();
                $table->string('country', 2)->nullable();
                $table->string('fingerprint', 64)->nullable();        // sha256 of digits, for dedupe

                // Scheme network transaction ID (issued at first auth);
                // required for subsequent off-session charges to avoid
                // SCA `authentication_required`.
                $table->string('network_transaction_id', 64)->nullable();

                $table->string('customer_email', 200)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['sandbox_merchant_id', 'customer_email']);
            });
        }

        if (! Schema::hasTable('sandbox_recordings')) {
            Schema::create('sandbox_recordings', function (Blueprint $table) {
                $table->id();
                $table->ulid('uuid')->unique();
                $table->foreignId('sandbox_merchant_id')->nullable()->constrained()->nullOnDelete();

                // Logical key for replay: {emulate}/{operation}/{scenario}.
                // Recording lookup is exact on this slug.
                $table->string('slug', 120)->index();
                $table->string('emulate', 32);
                $table->string('operation', 32);                      // charge|refund|status|capture|void
                $table->string('scenario', 60)->nullable();

                $table->json('request_snapshot')->nullable();         // headers + body, sanitised
                $table->unsignedSmallInteger('response_status');
                $table->json('response_headers')->nullable();
                $table->json('response_body');
                $table->string('source', 24)->default('manual');      // manual|capture-cli|test
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['emulate', 'operation', 'scenario']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_recordings');
        Schema::dropIfExists('sandbox_payment_methods');
        Schema::dropIfExists('sandbox_balance_ledger');
    }
};
