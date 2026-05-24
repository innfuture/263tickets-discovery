<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a bootstrap token to developer_accounts so portal mutations
 * stop being implicitly trustable to anyone who guesses an account
 * UUID. The plaintext is shown once at registration; subsequent
 * requests carry it as `Authorization: Bearer <token>`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('developer_accounts')) {
            return;
        }

        Schema::table('developer_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('developer_accounts', 'portal_bootstrap_token_hash')) {
                $table->string('portal_bootstrap_token_hash', 64)->nullable()->after('status');
            }
            if (! Schema::hasColumn('developer_accounts', 'portal_bootstrap_token_rotated_at')) {
                $table->timestamp('portal_bootstrap_token_rotated_at')->nullable()
                    ->after('portal_bootstrap_token_hash');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('developer_accounts')) {
            return;
        }

        Schema::table('developer_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('developer_accounts', 'portal_bootstrap_token_rotated_at')) {
                $table->dropColumn('portal_bootstrap_token_rotated_at');
            }
            if (Schema::hasColumn('developer_accounts', 'portal_bootstrap_token_hash')) {
                $table->dropColumn('portal_bootstrap_token_hash');
            }
        });
    }
};
