<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Offline ticket inventory adjustments are persisted as Batches:
 *
 *   - One row per adjustment (create / increase / decrease)
 *   - Every offline_ticket links to its source batch via batch_id
 *   - batch_number is monotonic per category — #1 = initial creation,
 *     #2+ = subsequent adjustments — so the timeline reads naturally
 *
 * Backfill: every existing offline_ticket gets attributed to a synthetic
 * "Initial creation" batch for its category. Without this the audit
 * timeline would have a black hole for pre-feature data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_ticket_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_category_id')
                ->constrained('ticket_categories')
                ->cascadeOnDelete();
            // Monotonic per category. Calculated in the service when the
            // batch is created (max(batch_number) + 1) inside a row lock.
            $table->unsignedInteger('batch_number');
            $table->string('operation', 16); // BatchOperation enum value
            $table->unsignedInteger('quantity'); // Requested amount
            // For decreases, the actual amount voided may be smaller than
            // requested if available inventory is less. Always equals
            // quantity on completed create/increase batches.
            $table->unsignedInteger('actual_quantity')->default(0);
            $table->string('status', 16)->default('pending'); // BatchStatus
            $table->unsignedTinyInteger('progress')->nullable();
            $table->string('reason', 280)->nullable();
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('error', 2000)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['ticket_category_id', 'batch_number'],
                'offline_batches_category_number_unique',
            );
            $table->index(['ticket_category_id', 'status'], 'offline_batches_status_idx');
        });

        Schema::table('offline_tickets', function (Blueprint $table) {
            // batch_id   — which batch minted this ticket
            // voided_by_batch_id — which batch (if any) voided it; powers
            //                      idempotent retry of decrease batches
            $table->foreignId('batch_id')
                ->nullable()
                ->after('ticket_category_id')
                ->constrained('offline_ticket_batches')
                ->nullOnDelete();
            $table->foreignId('voided_by_batch_id')
                ->nullable()
                ->after('batch_id')
                ->constrained('offline_ticket_batches')
                ->nullOnDelete();
            $table->index('batch_id');
            $table->index('voided_by_batch_id');
        });

        // Backfill — synthesise a Batch #1 ("Initial creation") for every
        // category that already has offline tickets, then attribute each
        // ticket to that batch. Idempotent: re-running the migration would
        // skip categories that already have a batch row.
        $now = now();
        $categoriesWithTickets = DB::table('offline_tickets')
            ->select('ticket_category_id')
            ->groupBy('ticket_category_id')
            ->pluck('ticket_category_id');

        foreach ($categoriesWithTickets as $categoryId) {
            $existing = DB::table('offline_ticket_batches')
                ->where('ticket_category_id', $categoryId)
                ->exists();

            if ($existing) {
                continue;
            }

            $count = DB::table('offline_tickets')
                ->where('ticket_category_id', $categoryId)
                ->count();

            $batchId = DB::table('offline_ticket_batches')->insertGetId([
                'ticket_category_id' => $categoryId,
                'batch_number' => 1,
                'operation' => 'create',
                'quantity' => $count,
                'actual_quantity' => $count,
                'status' => 'completed',
                'progress' => 100,
                'reason' => 'Backfilled from pre-batching schema.',
                'started_at' => $now,
                'completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('offline_tickets')
                ->where('ticket_category_id', $categoryId)
                ->whereNull('batch_id')
                ->update(['batch_id' => $batchId]);
        }
    }

    public function down(): void
    {
        Schema::table('offline_tickets', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropForeign(['voided_by_batch_id']);
            $table->dropColumn(['batch_id', 'voided_by_batch_id']);
        });

        Schema::dropIfExists('offline_ticket_batches');
    }
};
