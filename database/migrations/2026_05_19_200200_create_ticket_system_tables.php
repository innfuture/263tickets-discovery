<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();

            $table->uuid('organisation_id');
            $table->foreign('organisation_id')
                ->references('uuid')
                ->on('teams')
                ->restrictOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Identity
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('image_path', 2048)->nullable();

            // Ticket definition
            $table->string('admission_type', 32)->default('admit_one');
            $table->string('pass_type', 32)->default('single');

            // Inventory
            $table->unsignedInteger('offline_quantity')->default(0);
            $table->unsignedInteger('online_quantity')->default(0);

            // Pricing (base currency USD; per-currency overrides in ticket_currency_prices)
            $table->decimal('base_price', 10, 2)->default(0.00);
            $table->char('base_currency', 3)->default('USD');

            // Sales lifecycle
            $table->string('sale_status', 24)->default('active');
            $table->timestampTz('sales_start_at')->nullable();
            $table->timestampTz('sales_end_at')->nullable();

            // Visibility / limits
            $table->boolean('is_visible')->default(true);
            $table->unsignedTinyInteger('min_per_order')->default(1);
            $table->unsignedTinyInteger('max_per_order')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Background generation tracking
            $table->string('generation_status', 24)->default('pending');
            $table->unsignedSmallInteger('generation_progress')->default(0);

            // Scanning
            $table->unsignedInteger('scanned_count')->default(0);
            $table->string('requestor_ip', 45)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'sale_status']);
            $table->index(['event_id', 'is_visible', 'sort_order']);
        });

        Schema::create('ticket_currency_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_category_id')
                ->constrained('ticket_categories')
                ->cascadeOnDelete();
            $table->char('currency_code', 3);
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->unique(['ticket_category_id', 'currency_code']);
        });

        Schema::create('offline_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('ticket_category_id')
                ->constrained('ticket_categories')
                ->restrictOnDelete();

            // Denormalized for fast scanning queries without joins
            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();

            $table->uuid('organisation_id');
            $table->foreign('organisation_id')
                ->references('uuid')
                ->on('teams')
                ->restrictOnDelete();

            // Ticket identity
            $table->string('ticket_number', 32)->unique();
            $table->string('qr_payload', 512);    // Signed payload encoded in the QR code
            $table->string('serial', 64)->nullable()->unique();

            // Inherited from category (snapshot at generation time)
            $table->string('pass_type', 32);
            $table->string('admission_type', 32);

            // Scanning
            $table->unsignedSmallInteger('scan_count')->default(0);
            $table->timestamp('scanned_at')->nullable();
            $table->string('device_id', 125)->nullable();
            $table->unsignedSmallInteger('log_count')->default(0);

            // State
            $table->boolean('is_voided')->default(false);
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'ticket_number']);
            $table->index(['ticket_category_id', 'is_voided']);
        });

        Schema::create('ticket_discounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_category_id')
                ->constrained('ticket_categories')
                ->cascadeOnDelete();

            $table->string('name', 100);
            $table->string('discount_type', 24);    // percentage | fixed
            $table->decimal('value', 10, 2);

            // Trigger configuration
            $table->unsignedTinyInteger('min_quantity')->nullable();  // e.g. buy 5+ get 10% off
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);

            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        Schema::create('ticket_promo_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_category_id')
                ->constrained('ticket_categories')
                ->cascadeOnDelete();

            $table->string('code', 32);
            $table->string('discount_type', 24);    // percentage | fixed
            $table->decimal('value', 10, 2);

            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);

            $table->timestampTz('expires_at')->nullable();
            $table->json('auto_stop_criteria')->nullable();  // e.g. {"first_n_uses": 50}
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['ticket_category_id', 'code']);
            $table->index(['code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_promo_codes');
        Schema::dropIfExists('ticket_discounts');
        Schema::dropIfExists('offline_tickets');
        Schema::dropIfExists('ticket_currency_prices');
        Schema::dropIfExists('ticket_categories');
    }
};
