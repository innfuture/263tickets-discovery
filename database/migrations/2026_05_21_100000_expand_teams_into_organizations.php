<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repurposes the `teams` table as the platform's Organization entity. The
 * table name stays `teams` to keep every existing FK (events.organisation_id,
 * ad_campaigns.organisation_id, ticket_categories.organisation_id, etc.)
 * pointing where it already does — `teams.uuid` *is* the organization
 * identifier the rest of the schema already references.
 *
 * Idempotent: each `Schema::hasColumn` guard means a partially-applied
 * run from a previous attempt can be safely re-run to finish the job.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{name: string, ddl: \Closure(Blueprint): void}>
     */
    private array $columns;

    public function __construct()
    {
        $this->columns = [
            ['name' => 'tagline', 'ddl' => fn (Blueprint $t) => $t->string('tagline', 190)->nullable()->after('slug')],
            ['name' => 'description', 'ddl' => fn (Blueprint $t) => $t->text('description')->nullable()->after('tagline')],
            ['name' => 'organizer_type', 'ddl' => fn (Blueprint $t) => $t->string('organizer_type', 40)->nullable()->after('description')],
            ['name' => 'logo_path', 'ddl' => fn (Blueprint $t) => $t->string('logo_path', 2048)->nullable()->after('organizer_type')],
            ['name' => 'banner_path', 'ddl' => fn (Blueprint $t) => $t->string('banner_path', 2048)->nullable()->after('logo_path')],
            ['name' => 'contact_email', 'ddl' => fn (Blueprint $t) => $t->string('contact_email', 191)->nullable()->after('banner_path')],
            ['name' => 'support_email', 'ddl' => fn (Blueprint $t) => $t->string('support_email', 191)->nullable()->after('contact_email')],
            ['name' => 'contact_phone', 'ddl' => fn (Blueprint $t) => $t->string('contact_phone', 32)->nullable()->after('support_email')],
            ['name' => 'website_url', 'ddl' => fn (Blueprint $t) => $t->text('website_url')->nullable()->after('contact_phone')],
            // URL columns use TEXT instead of varchar(2048): six 2048-char
            // strings + the rest of the row exceeded MySQL's 65535-byte
            // in-row limit. TEXT stores out-of-row.
            ['name' => 'twitter_url', 'ddl' => fn (Blueprint $t) => $t->text('twitter_url')->nullable()->after('website_url')],
            ['name' => 'instagram_url', 'ddl' => fn (Blueprint $t) => $t->text('instagram_url')->nullable()->after('twitter_url')],
            ['name' => 'facebook_url', 'ddl' => fn (Blueprint $t) => $t->text('facebook_url')->nullable()->after('instagram_url')],
            ['name' => 'linkedin_url', 'ddl' => fn (Blueprint $t) => $t->text('linkedin_url')->nullable()->after('facebook_url')],
            ['name' => 'tiktok_url', 'ddl' => fn (Blueprint $t) => $t->text('tiktok_url')->nullable()->after('linkedin_url')],
            ['name' => 'youtube_url', 'ddl' => fn (Blueprint $t) => $t->text('youtube_url')->nullable()->after('tiktok_url')],
            ['name' => 'address_line_1', 'ddl' => fn (Blueprint $t) => $t->string('address_line_1', 160)->nullable()->after('youtube_url')],
            ['name' => 'address_line_2', 'ddl' => fn (Blueprint $t) => $t->string('address_line_2', 160)->nullable()->after('address_line_1')],
            ['name' => 'city', 'ddl' => fn (Blueprint $t) => $t->string('city', 80)->nullable()->after('address_line_2')],
            ['name' => 'region', 'ddl' => fn (Blueprint $t) => $t->string('region', 80)->nullable()->after('city')],
            ['name' => 'country_code', 'ddl' => fn (Blueprint $t) => $t->string('country_code', 2)->nullable()->after('region')],
            ['name' => 'postal_code', 'ddl' => fn (Blueprint $t) => $t->string('postal_code', 20)->nullable()->after('country_code')],
            ['name' => 'latitude', 'ddl' => fn (Blueprint $t) => $t->decimal('latitude', 10, 7)->nullable()->after('postal_code')],
            ['name' => 'longitude', 'ddl' => fn (Blueprint $t) => $t->decimal('longitude', 10, 7)->nullable()->after('latitude')],
            ['name' => 'tax_id', 'ddl' => fn (Blueprint $t) => $t->string('tax_id', 64)->nullable()->after('longitude')],
            ['name' => 'default_currency', 'ddl' => fn (Blueprint $t) => $t->char('default_currency', 3)->nullable()->after('tax_id')],
            ['name' => 'default_timezone', 'ddl' => fn (Blueprint $t) => $t->string('default_timezone', 64)->nullable()->after('default_currency')],
            ['name' => 'founded_year', 'ddl' => fn (Blueprint $t) => $t->unsignedSmallInteger('founded_year')->nullable()->after('default_timezone')],
            ['name' => 'is_verified', 'ddl' => fn (Blueprint $t) => $t->boolean('is_verified')->default(false)->after('founded_year')],
            ['name' => 'verified_at', 'ddl' => fn (Blueprint $t) => $t->timestamp('verified_at')->nullable()->after('is_verified')],
            ['name' => 'followers_count', 'ddl' => fn (Blueprint $t) => $t->unsignedInteger('followers_count')->default(0)->after('verified_at')],
        ];
    }

    public function up(): void
    {
        // A previous run of this migration created URL columns at
        // varchar(2048). That blew the in-row 65535-byte limit when
        // additional URL columns landed. ALTER any existing varchar URL
        // columns down to TEXT (out-of-row storage) before adding more.
        // Uses a raw SQL because Doctrine's column-modify path requires
        // the doctrine/dbal package which this codebase doesn't have.
        $urlColumns = ['website_url', 'twitter_url', 'instagram_url', 'facebook_url', 'linkedin_url'];
        foreach ($urlColumns as $col) {
            if (Schema::hasColumn('teams', $col)) {
                try {
                    \Illuminate\Support\Facades\DB::statement("ALTER TABLE `teams` MODIFY `{$col}` TEXT NULL");
                } catch (\Throwable) {
                    // Already TEXT or otherwise tolerable — keep going.
                }
            }
        }

        // Idempotent column adds — skip ones already present from a
        // prior partial run.
        foreach ($this->columns as $col) {
            if (! Schema::hasColumn('teams', $col['name'])) {
                Schema::table('teams', $col['ddl']);
            }
        }

        // Indexes — index methods don't have an `if not exists` helper, so
        // wrap each in a try/catch. Cheaper than introspecting SHOW INDEX
        // and the catch only fires on the re-run path.
        foreach (['organizer_type', 'country_code', 'is_verified'] as $col) {
            try {
                Schema::table('teams', function (Blueprint $t) use ($col) {
                    $t->index($col);
                });
            } catch (\Throwable) {
                // Index already exists from a prior run — fine.
            }
        }
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            foreach (['organizer_type', 'country_code', 'is_verified'] as $col) {
                try {
                    $table->dropIndex(['teams_'.$col.'_index']);
                } catch (\Throwable) {
                    // wasn't there
                }
            }

            $table->dropColumn(array_map(fn ($c) => $c['name'], $this->columns));
        });
    }
};
