<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->uuid('event_id')->unique();
            $table->string('slug', 140)->unique();

            $table->uuid('organisation_id');
            $table->foreign('organisation_id')
                ->references('uuid')
                ->on('teams')
                ->restrictOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('event_categories')
                ->nullOnDelete();

            $table->string('name', 126);
            $table->longText('description')->nullable();
            $table->string('short_description', 280)->nullable();

            $table->string('status', 24)->default('draft');
            $table->string('visibility', 24)->default('public');
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();

            $table->dateTimeTz('starts_at');
            $table->dateTimeTz('ends_at');
            $table->string('timezone', 64);
            $table->dateTimeTz('sales_start_at')->nullable();
            $table->dateTimeTz('sales_end_at')->nullable();

            $table->string('venue_name', 120)->nullable();
            $table->string('address_line_1', 160)->nullable();
            $table->string('address_line_2', 160)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('region', 80)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('online_url', 2048)->nullable();

            $table->string('banner_image_path', 2048)->nullable();
            $table->string('og_image_path', 2048)->nullable();

            $table->string('meta_title', 60)->nullable();
            $table->string('meta_description', 160)->nullable();
            $table->json('tags')->nullable();

            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedTinyInteger('minimum_age')->nullable();
            $table->text('refund_policy')->nullable();
            $table->text('terms')->nullable();

            $table->string('contact_email', 254)->nullable();
            $table->string('contact_phone', 32)->nullable();

            $table->unsignedInteger('tickets_sold_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('starts_at');
            $table->index(['status', 'visibility', 'starts_at']);
            $table->index(['organisation_id', 'status']);
            $table->index('country_code');
            $table->index('city');
        });

        DB::statement('ALTER TABLE events ADD CONSTRAINT events_chk_event_dates CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_chk_sales_dates CHECK (sales_start_at IS NULL OR sales_end_at IS NULL OR sales_end_at > sales_start_at)');
        DB::statement('ALTER TABLE events ADD CONSTRAINT events_chk_sales_before_end CHECK (sales_end_at IS NULL OR sales_end_at <= ends_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
