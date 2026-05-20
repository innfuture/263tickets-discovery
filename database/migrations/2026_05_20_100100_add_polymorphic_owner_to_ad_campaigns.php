
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decouples ad campaigns from being event-only by adding polymorphic
 * owner columns. `event_id` is retained for backward compatibility and
 * remains the canonical reference when the owner is an event.
 *
 * Backfills existing rows: owner_type = App\Models\Event, owner_id = event_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->string('owner_type', 191)->nullable()->after('event_id');
            $table->unsignedBigInteger('owner_id')->nullable()->after('owner_type');
            $table->index(['owner_type', 'owner_id'], 'ad_campaigns_owner_idx');
        });

        // Backfill existing event-owned rows
        DB::table('ad_campaigns')
            ->whereNotNull('event_id')
            ->whereNull('owner_type')
            ->update([
                'owner_type' => 'App\\Models\\Event',
                'owner_id' => DB::raw('event_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropIndex('ad_campaigns_owner_idx');
            $table->dropColumn(['owner_type', 'owner_id']);
        });
    }
};
