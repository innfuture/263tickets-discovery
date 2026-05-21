<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backing storage for two of the three real-persistence settings
 * features introduced in the settings restructure:
 *
 *   - users.notification_preferences (JSON) — per-user toggle map
 *     read/written by /settings/notifications. Keys correspond to
 *     server-side notification triggers; values are booleans. NULL
 *     means "use defaults" (defined in App\Models\User helper).
 *
 *   - organizations.door_pin (CHAR(8)) — short shared PIN that the
 *     scanner app uses to authenticate door-staff sessions on the
 *     venue side. Surfaced + regenerated from
 *     /settings/operations/check-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'notification_preferences')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('notification_preferences')->nullable();
            });
        }

        if (Schema::hasTable('organizations') && ! Schema::hasColumn('organizations', 'door_pin')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->char('door_pin', 8)->nullable()->after('default_timezone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'door_pin')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('door_pin');
            });
        }

        if (Schema::hasColumn('users', 'notification_preferences')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('notification_preferences');
            });
        }
    }
};
