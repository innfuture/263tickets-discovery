<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the brand_name column to organizations. The existing `name`
 * column carries the legal/registered entity (e.g. "InnFuture
 * Technologies Private Limited"); `brand_name` carries the
 * customer-facing trading name (e.g. "263tickets") shown on event
 * pages and the public profile. Nullable — falls back to `name`
 * when not set.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organizations', 'brand_name')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->string('brand_name', 120)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'brand_name')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('brand_name');
            });
        }
    }
};
