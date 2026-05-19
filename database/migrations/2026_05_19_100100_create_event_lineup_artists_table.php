<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_lineup_artists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('role', 60)->nullable();
            $table->text('bio')->nullable();
            $table->string('image_path', 2048)->nullable();
            $table->string('social_url', 2048)->nullable();
            $table->boolean('is_headliner')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'is_headliner', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_lineup_artists');
    }
};
