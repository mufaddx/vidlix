<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each side of a conversation is, so the inbox can be filtered by
 * All / Creator / Editor / Brand.
 *
 * The role lives on the participant, not on the conversation, because "who the
 * other side is" depends on who is looking: in a brand-to-creator thread the
 * brand's inbox shows Creator and the creator's shows Brand. One column on the
 * conversation could only ever be right for one of them.
 *
 * It is separate from the existing `role` column, which records the person's
 * part in the thread ('member', 'requester') rather than their marketplace role,
 * and it is nullable: a stranger who fills in a public form never says what they
 * are, and a guess behind a filter is worse than an honest blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->string('marketplace_role', 16)->nullable()->after('role');

            // Read state is per participant, not per conversation: the same
            // thread is unread for one side and read for the other.
            $table->timestamp('last_read_at')->nullable()->after('marketplace_role');

            $table->index(['user_id', 'marketplace_role']);
        });
    }

    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'marketplace_role']);
            $table->dropColumn(['marketplace_role', 'last_read_at']);
        });
    }
};
