<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('seo_keywords', 255)->nullable()->after('meta_description');
            $table->string('robots_directive', 48)->nullable()->after('seo_keywords');
            $table->string('og_type', 32)->nullable()->after('og_description');
            $table->string('og_locale', 10)->nullable()->after('og_type');
            $table->string('og_site_name', 100)->nullable()->after('og_locale');
            $table->string('twitter_title', 70)->nullable()->after('twitter_creator');
            $table->string('twitter_description', 200)->nullable()->after('twitter_title');
            $table->string('twitter_image', 2048)->nullable()->after('twitter_description');
            $table->json('schema_markup')->nullable()->after('twitter_image');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'seo_keywords',
                'robots_directive',
                'og_type',
                'og_locale',
                'og_site_name',
                'twitter_title',
                'twitter_description',
                'twitter_image',
                'schema_markup',
            ]);
        });
    }
};
