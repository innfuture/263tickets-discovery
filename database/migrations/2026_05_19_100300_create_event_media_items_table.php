<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_media_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();
            $table->string('type', 16); // image | video
            $table->string('path', 2048)->nullable();   // for uploaded images
            $table->string('url', 2048)->nullable();    // for hosted video (YouTube/Vimeo)
            $table->string('thumbnail_path', 2048)->nullable();
            $table->string('caption', 280)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_media_items');
    }
};
