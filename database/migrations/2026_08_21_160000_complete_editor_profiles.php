<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The rest of an editor's application.
 *
 * An editor could enter a bio, some software and a price. Everything a brand
 * actually decides on — how long they have been doing this, what they offer,
 * where they are, when they are free — had nowhere to live, so the marketplace
 * could list editors it could tell you almost nothing about.
 *
 * The review trail is here too. A rejection with no reason is a rejection
 * somebody cannot act on, and "under review" and "we need something else" are
 * different answers that were previously the same column value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editor_profiles', function (Blueprint $table) {
            $add = function (string $column, callable $define) use ($table) {
                if (! Schema::hasColumn('editor_profiles', $column)) {
                    $define($table);
                }
            };

            $add('years_experience', fn (Blueprint $t) => $t->unsignedSmallInteger('years_experience')->nullable());
            $add('services', fn (Blueprint $t) => $t->json('services')->nullable());
            $add('location', fn (Blueprint $t) => $t->string('location')->nullable());
            $add('languages', fn (Blueprint $t) => $t->json('languages')->nullable());
            $add('portfolio_url', fn (Blueprint $t) => $t->string('portfolio_url', 2000)->nullable());

            // The application trail. Kept on the profile rather than in a
            // separate table because there is only ever one live application
            // per editor, and a join for a single row buys nothing.
            $add('submitted_at', fn (Blueprint $t) => $t->timestamp('submitted_at')->nullable());
            $add('reviewed_at', fn (Blueprint $t) => $t->timestamp('reviewed_at')->nullable());
            $add('reviewed_by_user_id', fn (Blueprint $t) => $t->unsignedBigInteger('reviewed_by_user_id')->nullable());

            // Shown to the editor. A decision they cannot act on is a decision
            // that wastes both sides' time.
            $add('review_note', fn (Blueprint $t) => $t->text('review_note')->nullable());

            $add('terms_accepted_at', fn (Blueprint $t) => $t->timestamp('terms_accepted_at')->nullable());
        });

        /*
         | 'pending_review' was the only word for a submitted application, which
         | conflated "sent" with "somebody is reading it". They are separate
         | states now, and the existing rows become 'submitted' because that is
         | what is actually true of them.
         */
        DB::table('editor_profiles')
            ->where('application_status', 'pending_review')
            ->update(['application_status' => 'submitted']);
    }

    public function down(): void
    {
        DB::table('editor_profiles')
            ->whereIn('application_status', ['submitted', 'under_review', 'more_info'])
            ->update(['application_status' => 'pending_review']);

        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'years_experience', 'services', 'location', 'languages', 'portfolio_url',
                'submitted_at', 'reviewed_at', 'reviewed_by_user_id', 'review_note',
                'terms_accepted_at',
            ]);
        });
    }
};
