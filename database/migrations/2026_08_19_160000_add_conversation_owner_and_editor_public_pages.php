<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inquiry threads belonged to a creator profile, so editors could not have a
 * public page at all. A conversation now names the user who owns the inbox and
 * which side of their account it belongs to, which works for creators and
 * editors alike.
 *
 * creator_profile_id stays: creator pages still set it, and dropping it would
 * rewrite queries that are already covered by tests for no benefit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->after('creator_profile_id')->constrained('users')->nullOnDelete();
            $table->string('owner_scope', 16)->nullable()->after('owner_user_id');
            $table->index(['owner_user_id', 'owner_scope']);
        });

        // Backfill existing creator inquiry threads.
        DB::table('conversations')
            ->whereNotNull('creator_profile_id')
            ->update([
                'owner_user_id' => DB::raw('(select user_id from creator_profiles where creator_profiles.id = conversations.creator_profile_id)'),
                'owner_scope' => 'creator',
            ]);

        Schema::table('editor_profiles', function (Blueprint $table) {
            // Editors get the same public-page switch creators have.
            $table->string('visibility', 16)->default('private')->after('application_status');
            $table->boolean('accepts_inquiries')->default(true)->after('visibility');
        });
    }

    public function down(): void
    {
        Schema::table('editor_profiles', function (Blueprint $table) {
            $table->dropColumn(['visibility', 'accepts_inquiries']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['owner_user_id', 'owner_scope']);
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn('owner_scope');
        });
    }
};
