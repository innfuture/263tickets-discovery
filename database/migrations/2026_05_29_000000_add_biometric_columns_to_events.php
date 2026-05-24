<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event biometric policy. Read by the BiometricVerificationRule
 * to decide whether to require an on-device attestation at the gate.
 *
 * Stored as discrete columns (not metadata bag) so they're indexable
 * + visible in DB tooling, and so an admin UI can offer them without
 * a JSON editor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'biometric_required')) {
                $table->boolean('biometric_required')->default(false)->after('country_code');
            }
            if (! Schema::hasColumn('events', 'biometric_threshold')) {
                $table->decimal('biometric_threshold', 3, 2)->nullable()->after('biometric_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'biometric_threshold')) {
                $table->dropColumn('biometric_threshold');
            }
            if (Schema::hasColumn('events', 'biometric_required')) {
                $table->dropColumn('biometric_required');
            }
        });
    }
};
