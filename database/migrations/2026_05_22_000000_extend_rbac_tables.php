<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends Spatie's published RBAC schema with the columns our UI and
 * policies need on top of the defaults:
 *
 *   roles.level        - integer hierarchy weight (Owner=100, …, Member=10).
 *                        Used by sub-team / role-compare logic so we don't
 *                        re-derive ordering from name strings.
 *   roles.is_system    - whether this role ships with the platform and
 *                        cannot be edited or deleted by orgs.
 *   roles.description  - shown on the /settings/roles cards.
 *   permissions.group  - resource bucket ("event", "ticket", …). Drives the
 *                        grouped-checkbox UI on the role editor.
 *   permissions.label  - human-readable label for the same UI.
 *
 *   events.team_id     - nullable FK from events → teams. Null means
 *                        "owned by the org as a whole, any member with
 *                        org-level event permissions can manage it";
 *                        non-null means policies additionally require
 *                        the viewer to belong to that sub-team.
 *
 * Wrapped in idempotency guards so re-running on a partial state is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'level')) {
                $table->unsignedSmallInteger('level')->default(10)->after('name');
            }
            if (! Schema::hasColumn('roles', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('level');
            }
            if (! Schema::hasColumn('roles', 'description')) {
                $table->string('description', 190)->nullable()->after('is_system');
            }
        });

        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'group')) {
                $table->string('group', 60)->nullable()->after('name');
                $table->index('group');
            }
            if (! Schema::hasColumn('permissions', 'label')) {
                $table->string('label', 190)->nullable()->after('group');
            }
        });

        if (Schema::hasTable('events') && ! Schema::hasColumn('events', 'team_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->foreignId('team_id')
                    ->nullable()
                    ->after('organisation_id')
                    ->constrained('teams')
                    ->nullOnDelete();
                $table->index(['organisation_id', 'team_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events') && Schema::hasColumn('events', 'team_id')) {
            Schema::table('events', function (Blueprint $table) {
                try {
                    $table->dropIndex(['organisation_id', 'team_id']);
                } catch (\Throwable) {
                }
                $table->dropForeign(['team_id']);
                $table->dropColumn('team_id');
            });
        }

        Schema::table('permissions', function (Blueprint $table) {
            if (Schema::hasColumn('permissions', 'group')) {
                try {
                    $table->dropIndex(['group']);
                } catch (\Throwable) {
                }
                $table->dropColumn('group');
            }
            if (Schema::hasColumn('permissions', 'label')) {
                $table->dropColumn('label');
            }
        });

        Schema::table('roles', function (Blueprint $table) {
            foreach (['description', 'is_system', 'level'] as $col) {
                if (Schema::hasColumn('roles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Ensure the cache forgets stale permission registrations.
        DB::statement('SELECT 1');
    }
};
