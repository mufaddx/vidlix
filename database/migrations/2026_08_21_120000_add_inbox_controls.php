<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archive, mute, report and block.
 *
 * Archiving and muting sit on the participant rather than the conversation,
 * for the same reason read state does: one side filing a thread away says
 * nothing about whether the other side has. Putting them on the conversation
 * would let one person silence a thread for everybody in it.
 *
 * Blocking is between two people and outlives any single thread, so it is its
 * own table rather than a flag somewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('last_read_at');
            $table->timestamp('muted_at')->nullable()->after('archived_at');

            $table->index(['user_id', 'archived_at']);
        });

        Schema::create('conversation_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 64);
            $table->text('detail')->nullable();

            // open → reviewing → actioned | dismissed
            $table->string('status', 24)->default('open');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // One report per person per thread. A second report is the same
            // complaint again, not new information, and duplicates would bury
            // the queue rather than fill it.
            $table->unique(['conversation_id', 'reported_by_user_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 200)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'blocked_user_id']);
            $table->index(['blocked_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('conversation_reports');

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'archived_at']);
            $table->dropColumn(['archived_at', 'muted_at']);
        });
    }
};
