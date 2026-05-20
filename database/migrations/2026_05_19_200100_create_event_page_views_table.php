<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_page_views', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            // Visitor identity (no PII stored beyond IP for analytics)
            $table->string('session_id', 64)->nullable()->index();
            $table->string('ip_address', 45)->nullable(); // IPv4 / IPv6
            $table->text('user_agent')->nullable();

            // Geo (resolved async / via GeoIP lookup)
            $table->char('country_code', 2)->nullable()->index();
            $table->string('city', 80)->nullable();
            $table->string('region', 80)->nullable();
            $table->decimal('latitude', 7, 4)->nullable();
            $table->decimal('longitude', 7, 4)->nullable();

            // Traffic source
            $table->string('referrer', 2048)->nullable();
            $table->string('utm_source', 255)->nullable();
            $table->string('utm_medium', 255)->nullable();
            $table->string('utm_campaign', 255)->nullable();
            $table->string('utm_term', 255)->nullable();
            $table->string('utm_content', 255)->nullable();

            // Interaction type
            $table->string('event_type', 32)->default('page_view');  // page_view | click | conversion
            $table->json('event_data')->nullable(); // arbitrary context payload

            $table->timestamp('created_at')->useCurrent()->index();

            // Composite indexes for common dashboard queries
            $table->index(['event_id', 'event_type', 'created_at']);
            $table->index(['event_id', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_page_views');
    }
};
