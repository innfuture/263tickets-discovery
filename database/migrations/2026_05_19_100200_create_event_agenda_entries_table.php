<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_agenda_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();
            $table->dateTimeTz('starts_at');
            $table->dateTimeTz('ends_at')->nullable();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('host_name', 120)->nullable();
            $table->string('host_role', 40)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_agenda_entries');
    }
};
