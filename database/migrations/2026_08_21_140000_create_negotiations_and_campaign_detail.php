<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Negotiations as records, campaigns with the terms people actually argue about,
 * and milestones so a project is not one all-or-nothing payment.
 *
 * A negotiation used to be a proposal row with a version history, which is fine
 * for tracking a document but not for tracking a conversation about money.
 * What was missing is the thing both sides need to point at afterwards: who
 * offered what, when, and which offer is the one that was accepted.
 *
 * So offers are append-only. A counter-offer is a new row, never an edit, and
 * the accepted offer stays exactly as it was accepted — a deal whose terms can
 * be rewritten after the handshake is not a deal.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         | The terms a brand sets out and a creator reads before applying.
         | Most of this lived in the brief as free text, which meant none of it
         | could be filtered on or compared between campaigns.
         |
         | Some of these columns arrived with an earlier change, so each is
         | added only if it is missing: a migration that assumes the shape of
         | the table it alters fails on exactly the databases that have been
         | running longest.
         */
        Schema::table('campaigns', function (Blueprint $table) {
            $add = function (string $column, callable $define) use ($table) {
                if (! Schema::hasColumn('campaigns', $column)) {
                    $define($table);
                }
            };

            $add('campaign_type', fn (Blueprint $t) => $t->string('campaign_type', 32)->nullable());
            $add('deliverables', fn (Blueprint $t) => $t->json('deliverables')->nullable());
            $add('creator_count', fn (Blueprint $t) => $t->unsignedSmallInteger('creator_count')->nullable());
            $add('location', fn (Blueprint $t) => $t->string('location')->nullable());
            $add('work_mode', fn (Blueprint $t) => $t->string('work_mode', 16)->nullable());
            $add('min_followers', fn (Blueprint $t) => $t->unsignedBigInteger('min_followers')->nullable());
            $add('max_followers', fn (Blueprint $t) => $t->unsignedBigInteger('max_followers')->nullable());
            $add('min_engagement_bps', fn (Blueprint $t) => $t->unsignedSmallInteger('min_engagement_bps')->nullable());
            $add('platform', fn (Blueprint $t) => $t->string('platform', 32)->nullable());
            $add('usage_rights', fn (Blueprint $t) => $t->text('usage_rights')->nullable());
            $add('revision_terms', fn (Blueprint $t) => $t->text('revision_terms')->nullable());
            $add('payment_terms', fn (Blueprint $t) => $t->text('payment_terms')->nullable());
            $add('additional_requirements', fn (Blueprint $t) => $t->text('additional_requirements')->nullable());
            $add('published_at', fn (Blueprint $t) => $t->timestamp('published_at')->nullable());
            $add('closed_at', fn (Blueprint $t) => $t->timestamp('closed_at')->nullable());

            $table->index(['status', 'published_at']);
        });

        Schema::create('negotiations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_application_id')->nullable()->constrained()->nullOnDelete();

            // Both sides by user, not by profile: a negotiation is between two
            // people, and either of them may hold more than one profile.
            $table->foreignId('initiator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('counterparty_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('counterparty_scope', 16)->nullable();

            // negotiating | offer_sent | counter_offer | accepted
            // | rejected | expired | cancelled
            $table->string('status', 24)->default('negotiating');

            // Denormalised from the accepted offer so the common query — what
            // did we agree? — does not have to walk the offer history.
            $table->foreignId('accepted_offer_id')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['initiator_user_id', 'status']);
            $table->index(['counterparty_user_id', 'status']);
        });

        /*
         | Append-only. Every offer and counter-offer is its own row, so the
         | accepted terms are still readable exactly as they were accepted.
         */
        Schema::create('negotiation_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negotiation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');

            $table->foreignId('offered_by_user_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 8)->default('INR');

            $table->json('deliverables')->nullable();
            $table->date('deadline')->nullable();
            $table->unsignedSmallInteger('revision_limit')->nullable();
            $table->text('usage_rights')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(['negotiation_id', 'sequence']);
        });

        /*
         | Milestones. Without them a project is one payment at the end, which
         | is the arrangement neither side wants on anything longer than a day.
         */
        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);

            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->date('due_on')->nullable();

            // pending | in_progress | submitted | approved | paid | cancelled
            $table->string('status', 24)->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['project_id', 'position']);
        });

        /*
         | Saving somebody for later, and putting them on a shortlist for a
         | particular campaign. Two different acts: a favourite is personal and
         | permanent, a shortlist belongs to one campaign and ends with it.
         */
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 16);
            $table->unsignedBigInteger('subject_id');
            $table->timestamps();

            $table->unique(['user_id', 'subject_type', 'subject_id']);
        });

        Schema::create('shortlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 16);
            $table->unsignedBigInteger('subject_id');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shortlists');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('negotiation_offers');
        Schema::dropIfExists('negotiations');

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex(['status', 'published_at']);
            // Only the columns this migration is responsible for. The others
            // were here before it ran and are not its to remove.
            $table->dropColumn([
                'work_mode', 'min_followers', 'max_followers', 'min_engagement_bps',
                'revision_terms', 'payment_terms', 'additional_requirements',
                'published_at', 'closed_at',
            ]);
        });
    }
};
