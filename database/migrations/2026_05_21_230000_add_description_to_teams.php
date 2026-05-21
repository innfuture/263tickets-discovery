<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a short, optional description to sub-teams. Capped at 180
 * characters — the same length budget used for event short_description,
 * so a team description fits in the same UI primitives (cards, tooltips).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('teams', 'description')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->string('description', 180)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('teams', 'description')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
