<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_page_views', function (Blueprint $table) {
            // Target the interaction acted on (e.g. ticket category UUID,
            // ad campaign ID, section ID). Indexed for cohort queries.
            $table->string('event_target', 255)->nullable()->after('event_type');
            $table->index(['event_id', 'event_type', 'event_target'], 'epv_event_type_target_idx');

            // Numeric value attached to the event — e.g. conversion amount,
            // ticket count, watch-time seconds. Aggregatable in SQL.
            $table->decimal('event_value', 12, 2)->nullable()->after('event_target');

            // Renamed conceptually: event_data was being used inconsistently.
            // Keep it as-is for backward compatibility and use the new
            // event_metadata column for structured interaction context.
            $table->json('event_metadata')->nullable()->after('event_value');
        });
    }

    public function down(): void
    {
        Schema::table('event_page_views', function (Blueprint $table) {
            $table->dropIndex('epv_event_type_target_idx');
            $table->dropColumn(['event_target', 'event_value', 'event_metadata']);
        });
    }
};
