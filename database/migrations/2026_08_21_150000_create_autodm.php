<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram comment-to-DM automation.
 *
 * Deliberately separate from the existing `automations` tables, which are
 * marketplace automations and have nothing to do with Instagram. Folding the
 * two together would mean one execution log covering two products with
 * different failure modes and different provider limits.
 *
 * The shape follows what Instagram actually permits rather than what would be
 * convenient: a reply is tied to one comment, inside a bounded window, and only
 * with permissions Meta has granted. Anything the provider cannot do is
 * recorded as skipped with a reason — never as sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         | Media metadata only. No image or video bytes: Instagram serves those
         | from URLs that expire, and copying them would be both pointless and
         | a licence question nobody asked us to answer.
         */
        Schema::create('instagram_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instagram_account_id')->constrained()->cascadeOnDelete();

            $table->string('media_id')->unique();
            $table->string('media_type', 24)->nullable();
            $table->text('permalink')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->text('caption_excerpt')->nullable();
            $table->timestamp('published_at')->nullable();

            // ok | stale | error — what the last sync of this row achieved.
            $table->string('sync_status', 24)->default('ok');
            $table->text('sync_error')->nullable();
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();

            $table->index(['instagram_account_id', 'published_at']);
        });

        Schema::create('autodm_automations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instagram_account_id')->constrained()->cascadeOnDelete();

            // Null means every post on the account. A specific media id narrows
            // it to one, which is what most people actually want.
            $table->foreignId('instagram_media_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');

            // draft | active | inactive
            $table->string('status', 16)->default('draft');

            // Points at the version being executed. The version holds the terms;
            // this row holds only identity and state, so activating a new
            // version cannot silently rewrite what already ran.
            $table->foreignId('active_version_id')->nullable();

            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        /*
         | Immutable. Activating an automation freezes its terms into a version,
         | so a run months later can still say exactly which rules produced it.
         */
        Schema::create('autodm_automation_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('autodm_automation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');

            // any_comment | keywords
            $table->string('trigger_type', 24)->default('keywords');
            $table->json('keywords')->nullable();
            $table->boolean('whole_word')->default(false);

            $table->boolean('public_reply_enabled')->default(false);
            $table->text('public_reply_text')->nullable();

            $table->boolean('private_reply_enabled')->default(false);
            $table->text('private_reply_text')->nullable();
            $table->text('private_reply_url')->nullable();

            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['autodm_automation_id', 'version_number']);
        });

        /*
         | Every comment event we accepted, and what became of it.
         |
         | provider_event_id is unique, which is the whole of the idempotency
         | story: Instagram retries, and a retry must not send a second reply.
         */
        Schema::create('autodm_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_event_id')->unique();
            $table->foreignId('instagram_account_id')->nullable()->constrained()->nullOnDelete();

            $table->string('media_id')->nullable();
            $table->string('comment_id')->nullable();
            $table->string('commenter_id')->nullable();
            $table->text('comment_text')->nullable();

            // received | matched | unmatched | ignored
            $table->string('status', 24)->default('received');
            $table->timestamps();

            $table->index(['instagram_account_id', 'created_at']);
        });

        Schema::create('autodm_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('autodm_automation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('autodm_automation_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('autodm_event_id')->nullable()->constrained()->nullOnDelete();

            $table->string('action', 24);

            /*
             | received | validated | matched | queued | processing | sent
             | | skipped | failed | retry_scheduled | permanently_failed
             |
             | skipped is first-class and separate from failed: an action the
             | provider does not permit did not go wrong, it was never allowed,
             | and calling it a failure invites a retry that can never succeed.
             */
            $table->string('status', 24)->default('received');
            $table->string('reason_code', 48)->nullable();
            $table->text('detail')->nullable();

            $table->string('provider_response_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();

            $table->timestamps();

            $table->index(['autodm_automation_id', 'created_at']);
            $table->index(['status', 'next_attempt_at']);

            // One run per automation per comment per action. This is what stops
            // a duplicate webhook, or two automations racing, producing two
            // replies to the same person.
            $table->unique(['autodm_automation_id', 'autodm_event_id', 'action'], 'autodm_runs_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autodm_runs');
        Schema::dropIfExists('autodm_events');
        Schema::dropIfExists('autodm_automation_versions');
        Schema::dropIfExists('autodm_automations');
        Schema::dropIfExists('instagram_media');
    }
};
