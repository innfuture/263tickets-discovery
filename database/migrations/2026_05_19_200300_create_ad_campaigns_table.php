<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->uuid('organisation_id');
            $table->foreign('organisation_id')
                ->references('uuid')
                ->on('teams')
                ->restrictOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Campaign identity
            $table->string('name', 120);
            $table->string('platform', 32);         // google_ads | meta_ads | youtube
            $table->string('campaign_status', 24)->default('draft');

            // Budget
            $table->decimal('budget_daily', 10, 2)->nullable();
            $table->decimal('budget_total', 10, 2)->nullable();
            $table->char('budget_currency', 3)->default('USD');

            // Schedule
            $table->timestampTz('runs_from')->nullable();
            $table->timestampTz('runs_until')->nullable();

            // Targeting spec (demographics, locations, interests, custom audiences)
            $table->json('targeting')->nullable();

            // Ad creative assets (headlines, descriptions, images, videos)
            $table->json('creative_assets')->nullable();

            // Platform-specific config (bid strategy, ad type, etc.)
            $table->json('platform_config')->nullable();

            // External identifiers returned by the platform after creation
            $table->string('external_campaign_id', 255)->nullable();
            $table->string('external_ad_account_id', 255)->nullable();
            $table->string('external_ad_set_id', 255)->nullable();

            // Cached performance metrics (synced periodically)
            $table->json('metrics')->nullable();
            $table->timestampTz('metrics_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'platform', 'campaign_status']);
            $table->index(['organisation_id', 'campaign_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
    }
};
