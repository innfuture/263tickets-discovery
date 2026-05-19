<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // SEO / social
            $table->string('og_title', 60)->nullable()->after('og_image_path');
            $table->string('og_description', 160)->nullable()->after('og_title');
            $table->string('twitter_card', 24)->nullable()->after('og_description');
            $table->string('twitter_creator', 32)->nullable()->after('twitter_card');
            $table->string('canonical_url', 2048)->nullable()->after('twitter_creator');

            // Highlights
            $table->dateTimeTz('doors_open_at')->nullable()->after('ends_at');
            $table->text('parking_info')->nullable()->after('minimum_age');
            $table->text('age_requirement_details')->nullable()->after('parking_info');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'og_title',
                'og_description',
                'twitter_card',
                'twitter_creator',
                'canonical_url',
                'doors_open_at',
                'parking_info',
                'age_requirement_details',
            ]);
        });
    }
};
