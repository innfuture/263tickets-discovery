<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-5 — production-hardening tables.
 *
 *   webhook_outbox              Transactional outbox so domain events
 *                                survive a queue outage between DB
 *                                commit and dispatch.
 *   automation_idempotency_keys Replay-safe POST /automations/* —
 *                                duplicate keys return the cached
 *                                response without re-executing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webhook_outbox')) {
            Schema::create('webhook_outbox', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('event_type', 80);
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->json('payload');
                // pending → dispatched → failed (after max retries)
                $table->string('status', 16)->default('pending');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('available_at')->nullable();
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'available_at']);
                $table->index(['organization_id', 'event_type']);
            });
        }

        if (! Schema::hasTable('automation_idempotency_keys')) {
            Schema::create('automation_idempotency_keys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('automation_token_id')
                    ->constrained()->cascadeOnDelete();
                $table->string('key', 80);
                $table->string('request_hash', 64);
                $table->unsignedSmallInteger('response_status');
                $table->json('response_body')->nullable();
                $table->timestamps();
                // Idempotency-Key is scoped per-token so two orgs
                // (or rotated tokens) won't collide.
                $table->unique(['automation_token_id', 'key'], 'automation_idem_unique');
                $table->index(['created_at']); // sweep
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_idempotency_keys');
        Schema::dropIfExists('webhook_outbox');
    }
};
