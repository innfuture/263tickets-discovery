<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promote the parent entity out of `teams` into its own `organizations`
 * table. The hierarchy becomes Organization → Teams → Members:
 *
 *   - `organizations` owns the brand profile (logo, banner, social,
 *     address, business attrs) previously hanging off `teams`.
 *   - `teams` demotes to a sub-team within an organization (gains
 *     `organization_id` FK; profile columns are dropped).
 *   - `organization_members` is the new top-level pivot; `team_members`
 *     becomes a sub-team-scoped assignment.
 *   - Existing `organisation_id` FKs already store the team's UUID;
 *     each org row carries the same UUID forward 1:1 so the FK target
 *     can move from `teams.uuid` to `organizations.uuid` without any
 *     row-level remapping.
 *
 * Every step is wrapped in an existence guard so a partial run can be
 * safely resumed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_personal')->default(false);

                $table->string('tagline', 190)->nullable();
                $table->text('description')->nullable();
                $table->string('organizer_type', 40)->nullable();

                $table->string('logo_path', 2048)->nullable();
                $table->string('banner_path', 2048)->nullable();

                $table->string('contact_email', 191)->nullable();
                $table->string('support_email', 191)->nullable();
                $table->string('contact_phone', 32)->nullable();

                $table->text('website_url')->nullable();
                $table->text('twitter_url')->nullable();
                $table->text('instagram_url')->nullable();
                $table->text('facebook_url')->nullable();
                $table->text('linkedin_url')->nullable();
                $table->text('tiktok_url')->nullable();
                $table->text('youtube_url')->nullable();

                $table->string('address_line_1', 160)->nullable();
                $table->string('address_line_2', 160)->nullable();
                $table->string('city', 80)->nullable();
                $table->string('region', 80)->nullable();
                $table->string('country_code', 2)->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();

                $table->string('tax_id', 64)->nullable();
                $table->char('default_currency', 3)->nullable();
                $table->string('default_timezone', 64)->nullable();
                $table->unsignedSmallInteger('founded_year')->nullable();

                $table->boolean('is_verified')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->unsignedInteger('followers_count')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('organizer_type');
                $table->index('country_code');
                $table->index('is_verified');
            });

            $this->backfillOrganizationsFromTeams();
        }

        if (! Schema::hasTable('organization_members')) {
            Schema::create('organization_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role');
                $table->timestamps();

                $table->unique(['organization_id', 'user_id']);
            });

            // Backfill from team_members through the matching team→org uuid.
            DB::statement(<<<'SQL'
                INSERT INTO organization_members (organization_id, user_id, role, created_at, updated_at)
                SELECT o.id, tm.user_id, tm.role, tm.created_at, tm.updated_at
                FROM team_members tm
                INNER JOIN teams t ON t.id = tm.team_id
                INNER JOIN organizations o ON o.uuid = t.uuid
            SQL);
        }

        if (! Schema::hasColumn('users', 'current_organization_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('current_organization_id')
                    ->nullable()
                    ->after('current_team_id')
                    ->constrained('organizations')
                    ->nullOnDelete();
            });

            DB::statement(<<<'SQL'
                UPDATE users u
                INNER JOIN teams t ON t.id = u.current_team_id
                INNER JOIN organizations o ON o.uuid = t.uuid
                SET u.current_organization_id = o.id
                WHERE u.current_team_id IS NOT NULL
            SQL);
        }

        if (! Schema::hasColumn('teams', 'organization_id')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizations')
                    ->cascadeOnDelete();
            });

            DB::statement(<<<'SQL'
                UPDATE teams t
                INNER JOIN organizations o ON o.uuid = t.uuid
                SET t.organization_id = o.id
            SQL);
        }

        // Rewire the organisation_id FKs. Uses information_schema to find
        // the existing constraint name (which may point to teams.uuid or
        // already to organizations.uuid depending on re-run state).
        foreach (['events', 'ad_campaigns', 'ticket_categories', 'offline_tickets'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'organisation_id')) {
                continue;
            }

            $fk = DB::selectOne(
                'SELECT kcu.CONSTRAINT_NAME AS name, kcu.REFERENCED_TABLE_NAME AS ref
                 FROM information_schema.KEY_COLUMN_USAGE kcu
                 WHERE kcu.TABLE_SCHEMA = DATABASE() AND kcu.TABLE_NAME = ? AND kcu.COLUMN_NAME = ?
                   AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
                 LIMIT 1',
                [$tableName, 'organisation_id'],
            );

            if (! $fk || $fk->ref === 'organizations') {
                continue; // Already pointing at organizations.
            }

            DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$fk->name}`");

            Schema::table($tableName, function (Blueprint $t) {
                $t->foreign('organisation_id')
                    ->references('uuid')
                    ->on('organizations')
                    ->restrictOnDelete();
            });
        }

        // team_invitations → organization_invitations. The FK
        // constraint name does NOT auto-rename when a table is renamed,
        // so we resolve it dynamically.
        if (Schema::hasTable('team_invitations') && ! Schema::hasTable('organization_invitations')) {
            Schema::rename('team_invitations', 'organization_invitations');
        }

        if (Schema::hasTable('organization_invitations') && Schema::hasColumn('organization_invitations', 'team_id')) {
            $teamIdFk = DB::selectOne(
                'SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
                 LIMIT 1',
                ['organization_invitations', 'team_id'],
            );

            if ($teamIdFk) {
                DB::statement('ALTER TABLE `organization_invitations` DROP FOREIGN KEY `'.$teamIdFk->name.'`');
            }

            Schema::table('organization_invitations', function (Blueprint $table) {
                $table->renameColumn('team_id', 'organization_id');
            });

            DB::statement(<<<'SQL'
                UPDATE organization_invitations oi
                INNER JOIN teams t ON t.id = oi.organization_id
                INNER JOIN organizations o ON o.uuid = t.uuid
                SET oi.organization_id = o.id
            SQL);

            Schema::table('organization_invitations', function (Blueprint $table) {
                $table->foreign('organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->cascadeOnDelete();
            });
        }

        // Drop profile columns from teams — they live on organizations
        // now. Indexes go first or MySQL refuses the drop. The index
        // name lookup goes through information_schema rather than
        // Laravel's helper so a partial-run state (some indexes already
        // dropped) doesn't blow up the migration.
        if (Schema::hasTable('teams')) {
            foreach (['organizer_type', 'country_code', 'is_verified'] as $col) {
                $existing = DB::select(
                    'SELECT INDEX_NAME AS name FROM information_schema.STATISTICS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND INDEX_NAME != ?',
                    ['teams', $col, 'PRIMARY'],
                );

                foreach ($existing as $idx) {
                    DB::statement('ALTER TABLE `teams` DROP INDEX `'.$idx->name.'`');
                }
            }

            $profileCols = [
                'tagline', 'description', 'organizer_type',
                'logo_path', 'banner_path',
                'contact_email', 'support_email', 'contact_phone',
                'website_url', 'twitter_url', 'instagram_url', 'facebook_url',
                'linkedin_url', 'tiktok_url', 'youtube_url',
                'address_line_1', 'address_line_2', 'city', 'region',
                'country_code', 'postal_code', 'latitude', 'longitude',
                'tax_id', 'default_currency', 'default_timezone', 'founded_year',
                'is_verified', 'verified_at', 'followers_count',
                'uuid', // sub-teams don't need an external UUID — the parent org carries it.
            ];

            foreach ($profileCols as $col) {
                if (Schema::hasColumn('teams', $col)) {
                    if ($col === 'uuid') {
                        try {
                            Schema::table('teams', fn (Blueprint $t) => $t->dropUnique(['uuid']));
                        } catch (\Throwable) {
                        }
                    }
                    Schema::table('teams', fn (Blueprint $t) => $t->dropColumn($col));
                }
            }
        }
    }

    public function down(): void
    {
        // Best-effort reverse: re-add `uuid` on teams, repoint dependent
        // FKs back to teams.uuid, rename org_invitations back, drop
        // organization_members + organizations. Profile data is NOT
        // restored to teams (that would require copying every column
        // back — fine for dev rollback, but flag if you ever roll this
        // back in prod).

        if (Schema::hasTable('teams') && ! Schema::hasColumn('teams', 'uuid')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });

            DB::statement(<<<'SQL'
                UPDATE teams t
                INNER JOIN organizations o ON o.id = t.organization_id
                SET t.uuid = o.uuid
            SQL);

            Schema::table('teams', function (Blueprint $table) {
                $table->unique('uuid');
            });
        }

        foreach (['events', 'ad_campaigns', 'ticket_categories'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'organisation_id')) {
                continue;
            }

            $fk = DB::selectOne(
                'SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
                 LIMIT 1',
                [$tableName, 'organisation_id'],
            );

            if ($fk) {
                DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$fk->name}`");
            }

            Schema::table($tableName, function (Blueprint $t) {
                $t->foreign('organisation_id')
                    ->references('uuid')
                    ->on('teams')
                    ->restrictOnDelete();
            });
        }

        if (Schema::hasTable('organization_invitations')) {
            $orgIdFk = DB::selectOne(
                'SELECT CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
                 LIMIT 1',
                ['organization_invitations', 'organization_id'],
            );
            if ($orgIdFk) {
                DB::statement('ALTER TABLE `organization_invitations` DROP FOREIGN KEY `'.$orgIdFk->name.'`');
            }

            DB::statement(<<<'SQL'
                UPDATE organization_invitations oi
                INNER JOIN organizations o ON o.id = oi.organization_id
                INNER JOIN teams t ON t.uuid = o.uuid
                SET oi.organization_id = t.id
            SQL);

            Schema::table('organization_invitations', function (Blueprint $table) {
                $table->renameColumn('organization_id', 'team_id');
            });
            Schema::table('organization_invitations', function (Blueprint $table) {
                $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            });
            Schema::rename('organization_invitations', 'team_invitations');
        }

        if (Schema::hasColumn('users', 'current_organization_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['current_organization_id']);
                $table->dropColumn('current_organization_id');
            });
        }

        if (Schema::hasColumn('teams', 'organization_id')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            });
        }

        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');
    }

    /**
     * Copy every existing team into a matching organization row,
     * preserving the UUID so that all `organisation_id` FK values across
     * the schema continue to point to the correct entity.
     */
    private function backfillOrganizationsFromTeams(): void
    {
        if (! Schema::hasTable('teams')) {
            return;
        }

        DB::table('teams')->orderBy('id')->chunkById(200, function ($teams) {
            $rows = [];
            foreach ($teams as $t) {
                $rows[] = [
                    'uuid' => $t->uuid,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'is_personal' => $t->is_personal,
                    'tagline' => $t->tagline ?? null,
                    'description' => $t->description ?? null,
                    'organizer_type' => $t->organizer_type ?? null,
                    'logo_path' => $t->logo_path ?? null,
                    'banner_path' => $t->banner_path ?? null,
                    'contact_email' => $t->contact_email ?? null,
                    'support_email' => $t->support_email ?? null,
                    'contact_phone' => $t->contact_phone ?? null,
                    'website_url' => $t->website_url ?? null,
                    'twitter_url' => $t->twitter_url ?? null,
                    'instagram_url' => $t->instagram_url ?? null,
                    'facebook_url' => $t->facebook_url ?? null,
                    'linkedin_url' => $t->linkedin_url ?? null,
                    'tiktok_url' => $t->tiktok_url ?? null,
                    'youtube_url' => $t->youtube_url ?? null,
                    'address_line_1' => $t->address_line_1 ?? null,
                    'address_line_2' => $t->address_line_2 ?? null,
                    'city' => $t->city ?? null,
                    'region' => $t->region ?? null,
                    'country_code' => $t->country_code ?? null,
                    'postal_code' => $t->postal_code ?? null,
                    'latitude' => $t->latitude ?? null,
                    'longitude' => $t->longitude ?? null,
                    'tax_id' => $t->tax_id ?? null,
                    'default_currency' => $t->default_currency ?? null,
                    'default_timezone' => $t->default_timezone ?? null,
                    'founded_year' => $t->founded_year ?? null,
                    'is_verified' => $t->is_verified ?? false,
                    'verified_at' => $t->verified_at ?? null,
                    'followers_count' => $t->followers_count ?? 0,
                    'created_at' => $t->created_at,
                    'updated_at' => $t->updated_at,
                    'deleted_at' => $t->deleted_at ?? null,
                ];
            }
            DB::table('organizations')->insert($rows);
        });
    }
};
