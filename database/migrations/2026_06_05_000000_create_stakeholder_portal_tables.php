<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-7 — Stakeholder Portal.
 *
 * Stakeholders are non-organizer commercial entities that plug into
 * events: sponsors, media partners, food/beverage vendors, security
 * companies, AV crews, photographers, influencers. Each type has its
 * own workflow (StakeholderWorkflow strategy), but they all share:
 *
 *   stakeholder            account + identity + verification
 *   stakeholder_profiles   public-facing brand info, portfolio, socials
 *   stakeholder_services   catalogue of what they offer (price + meta)
 *   stakeholder_invitations  event → stakeholder direction
 *   stakeholder_applications stakeholder → event direction
 *   event_stakeholder_engagements   the contracted relationship
 *   stakeholder_deliverables tasks / milestones inside an engagement
 *   stakeholder_payments     scheduled + paid amounts per engagement
 *   stakeholder_reviews      post-event two-way reviews
 *   stakeholder_documents    contracts, insurance certs, W9s
 *
 * Like Buyers, stakeholders auth via magic-link — no password.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stakeholders')) {
            Schema::create('stakeholders', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                // sponsor | media_partner | food_vendor | service_provider
                // | influencer | photographer | merchandiser
                $table->string('type', 40);
                $table->string('email', 191)->unique();
                $table->string('name', 191);
                $table->string('company', 191)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('country_code', 2)->nullable();
                // pending → verified → suspended; verification is
                // marketplace-side review of business legitimacy.
                $table->string('status', 20)->default('pending');
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->string('suspended_reason', 255)->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip', 45)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['type', 'status']);
            });
        }

        if (! Schema::hasTable('stakeholder_sessions')) {
            Schema::create('stakeholder_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('stakeholder_id')->constrained()->cascadeOnDelete();
                $table->char('token_hash', 64)->unique();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('last_active_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
                $table->index(['stakeholder_id', 'revoked_at']);
            });
        }

        if (! Schema::hasTable('stakeholder_login_tokens')) {
            Schema::create('stakeholder_login_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stakeholder_id')->nullable()
                    ->constrained()->cascadeOnDelete();
                $table->string('email', 191);
                $table->char('token_hash', 64)->unique();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();
                $table->index(['email', 'consumed_at']);
            });
        }

        if (! Schema::hasTable('stakeholder_profiles')) {
            Schema::create('stakeholder_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stakeholder_id')->constrained()->cascadeOnDelete();
                $table->text('bio')->nullable();
                $table->string('logo_path', 500)->nullable();
                $table->string('banner_path', 500)->nullable();
                $table->string('website', 500)->nullable();
                $table->json('social_links')->nullable();
                $table->json('portfolio_items')->nullable();
                $table->json('case_studies')->nullable();
                $table->json('service_areas')->nullable(); // cities / countries
                $table->json('languages')->nullable();
                // Free-form public tags for marketplace search.
                $table->json('tags')->nullable();
                $table->boolean('accepting_invitations')->default(true);
                $table->boolean('listed_in_marketplace')->default(true);
                $table->timestamps();
                $table->unique('stakeholder_id');
            });
        }

        if (! Schema::hasTable('stakeholder_services')) {
            Schema::create('stakeholder_services', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('stakeholder_id')->constrained()->cascadeOnDelete();
                $table->string('name', 191);
                $table->text('description')->nullable();
                $table->unsignedInteger('base_price_cents')->nullable();
                $table->string('currency', 6)->nullable();
                // hourly | daily | event | package | quote_only
                $table->string('pricing_model', 24)->default('quote_only');
                $table->json('attributes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['stakeholder_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('stakeholder_invitations')) {
            Schema::create('stakeholder_invitations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('stakeholder_id')->constrained()->cascadeOnDelete();
                $table->foreignId('invited_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->string('engagement_type', 40); // sponsor|media|vendor|service
                $table->text('message')->nullable();
                $table->json('proposed_terms')->nullable();
                // pending → accepted → engagement | declined | expired
                $table->string('status', 20)->default('pending');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();
                $table->index(['stakeholder_id', 'status']);
                $table->index(['event_id', 'status']);
            });
        }

        if (! Schema::hasTable('stakeholder_applications')) {
            Schema::create('stakeholder_applications', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('stakeholder_id')->constrained()->cascadeOnDelete();
                $table->string('engagement_type', 40);
                $table->text('pitch')->nullable();
                $table->json('proposed_terms')->nullable();
                // submitted → under_review → approved → engagement |
                // declined | withdrawn
                $table->string('status', 20)->default('submitted');
                $table->timestamp('responded_at')->nullable();
                $table->foreignId('reviewed_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->text('review_notes')->nullable();
                $table->timestamps();
                $table->index(['event_id', 'status']);
                $table->index(['stakeholder_id', 'status']);
            });
        }

        if (! Schema::hasTable('event_stakeholder_engagements')) {
            Schema::create('event_stakeholder_engagements', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('stakeholder_id')->constrained()->cascadeOnDelete();
                $table->foreignId('stakeholder_invitation_id')->nullable()
                    ->constrained()->nullOnDelete();
                $table->foreignId('stakeholder_application_id')->nullable()
                    ->constrained()->nullOnDelete();
                $table->string('engagement_type', 40);
                $table->string('tier', 40)->nullable(); // sponsor tier etc.
                $table->json('terms');
                $table->unsignedInteger('agreed_amount_cents')->nullable();
                $table->string('currency', 6)->nullable();
                // active → completed | cancelled | disputed
                $table->string('status', 20)->default('active');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
                $table->unique(['event_id', 'stakeholder_id', 'engagement_type'], 'engagement_unique');
                $table->index(['status']);
            });
        }

        if (! Schema::hasTable('stakeholder_deliverables')) {
            Schema::create('stakeholder_deliverables', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('event_stakeholder_engagement_id')->constrained()->cascadeOnDelete();
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->timestamp('due_at')->nullable();
                // pending → in_progress → submitted → approved | rejected
                $table->string('status', 20)->default('pending');
                $table->text('submission_notes')->nullable();
                $table->json('submission_attachments')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('approved_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamps();
                $table->index(['event_stakeholder_engagement_id', 'status']);
            });
        }

        if (! Schema::hasTable('stakeholder_payments')) {
            Schema::create('stakeholder_payments', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('event_stakeholder_engagement_id')->constrained()->cascadeOnDelete();
                $table->string('description', 191);
                $table->unsignedInteger('amount_cents');
                $table->string('currency', 6);
                $table->timestamp('due_at')->nullable();
                // scheduled → processing → paid | failed | cancelled
                $table->string('status', 20)->default('scheduled');
                $table->foreignId('payment_transaction_id')->nullable()
                    ->constrained()->nullOnDelete();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                $table->index(['event_stakeholder_engagement_id', 'status']);
            });
        }

        if (! Schema::hasTable('stakeholder_reviews')) {
            Schema::create('stakeholder_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_stakeholder_engagement_id')->constrained()->cascadeOnDelete();
                // organizer_to_stakeholder | stakeholder_to_organizer
                $table->string('direction', 30);
                $table->unsignedTinyInteger('rating'); // 1..5
                $table->text('comment')->nullable();
                $table->json('criteria_ratings')->nullable();
                $table->boolean('is_public')->default(true);
                $table->foreignId('author_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['event_stakeholder_engagement_id', 'direction'], 'review_unique_direction');
            });
        }

        if (! Schema::hasTable('stakeholder_documents')) {
            Schema::create('stakeholder_documents', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('stakeholder_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_stakeholder_engagement_id')->nullable()
                    ->constrained()->cascadeOnDelete();
                // contract | insurance | tax_form | permit | nda | other
                $table->string('kind', 32);
                $table->string('name', 191);
                $table->string('storage_path', 500);
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->foreignId('uploaded_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['stakeholder_id', 'kind']);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'stakeholder_documents', 'stakeholder_reviews',
            'stakeholder_payments', 'stakeholder_deliverables',
            'event_stakeholder_engagements', 'stakeholder_applications',
            'stakeholder_invitations', 'stakeholder_services',
            'stakeholder_profiles', 'stakeholder_login_tokens',
            'stakeholder_sessions', 'stakeholders',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
