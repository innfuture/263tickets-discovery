<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();
            $table->string('name', 160);
            // SponsorTier enum value (title / presenting / platinum / ...).
            // Stored as string so a future tier can be added without an ALTER.
            $table->string('tier', 32)->default('partner');
            $table->string('logo_path', 2048)->nullable();
            $table->string('website_url', 2048)->nullable();
            $table->string('social_url', 2048)->nullable();
            // Short tagline — shown beneath the name on the public page.
            $table->string('description', 280)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'tier', 'sort_order'], 'event_sponsors_grouping_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_sponsors');
    }
};
