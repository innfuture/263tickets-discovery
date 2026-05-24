<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-8 — innovation bets.
 *
 *   event_embeddings        Vector embeddings for semantic event
 *                           search + recommendations (pgvector when
 *                           available, JSON-array fallback otherwise).
 *   event_predictions       Output of the SelloutPredictor — read by
 *                           the organizer dashboard + Developer API.
 *   event_drafts            CRDT state for the real-time collaborative
 *                           event editor (Yjs document blobs).
 *   engagement_disputes     Stakeholder ↔ organizer dispute resolution
 *                           workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_embeddings')) {
            Schema::create('event_embeddings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                // Model the embedding was generated against — lets us
                // re-embed cleanly when we swap providers.
                $table->string('model', 80);
                // 1536-dim OpenAI embeddings encode as ~25KB JSON.
                // On Postgres-with-pgvector, swap to a `vector(1536)`
                // column via a per-driver migration override.
                $table->json('embedding');
                $table->string('content_hash', 64); // short-circuit re-embedding
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();
                $table->unique(['event_id', 'model'], 'event_embeddings_unique_per_model');
            });
        }

        if (! Schema::hasTable('event_predictions')) {
            Schema::create('event_predictions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                // sellout_eta | tickets_at_door | optimal_price
                $table->string('prediction_type', 40);
                $table->json('value');
                $table->decimal('confidence', 4, 3)->nullable(); // 0.000..1.000
                $table->string('model_version', 40)->default('linear-velocity-v1');
                $table->timestamp('computed_at')->nullable();
                $table->timestamps();
                $table->unique(['event_id', 'prediction_type'], 'event_predictions_unique');
            });
        }

        if (! Schema::hasTable('event_drafts')) {
            Schema::create('event_drafts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                // Y.js binary doc state. base64-encoded so the JSON
                // surface can ferry it cleanly. Client materialises
                // the actual Yjs.Doc from this.
                $table->longText('ydoc_base64')->nullable();
                $table->unsignedInteger('version')->default(0);
                $table->foreignId('last_editor_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('engagement_disputes')) {
            Schema::create('engagement_disputes', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('event_stakeholder_engagement_id')
                    ->constrained()->cascadeOnDelete();
                // organizer | stakeholder
                $table->string('raised_by_type', 24);
                $table->unsignedBigInteger('raised_by_id');
                // Free-form one-word categorisation: non_delivery |
                // payment_dispute | quality_issue | breach | other
                $table->string('reason_code', 40);
                $table->text('initial_statement');
                $table->json('evidence')->nullable();
                // open → under_review → resolved | withdrawn
                $table->string('status', 24)->default('open');
                $table->string('resolution', 24)->nullable();
                $table->text('resolution_notes')->nullable();
                $table->foreignId('resolved_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['event_stakeholder_engagement_id', 'status'], 'engagement_disputes_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_disputes');
        Schema::dropIfExists('event_drafts');
        Schema::dropIfExists('event_predictions');
        Schema::dropIfExists('event_embeddings');
    }
};
