<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();
            $table->string('name', 80);
            // Lucide icon name (e.g. "wifi", "parking-square"). The frontend
            // maps this string to the matching icon component; an empty/unknown
            // value falls back to a generic check icon.
            $table->string('icon', 40)->nullable();
            $table->string('description', 280)->nullable();
            // Loose grouping for filtering / future analytics ("facility",
            // "accessibility", "food", "tech", "parking", "services").
            $table->string('category', 40)->nullable();
            // Mark a small set of premium amenities to surface in a "featured"
            // strip at the top of the amenities grid.
            $table->boolean('is_highlighted')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'is_highlighted', 'sort_order'], 'event_amenities_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_amenities');
    }
};
