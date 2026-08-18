<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('company')->nullable();
            $table->timestamps();
            $table->unique('email');
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_uuid')->unique();
            $table->string('channel', 32);
            $table->string('subject')->nullable();
            $table->string('status', 32)->default('open');
            $table->foreignId('creator_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('external_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('routing_token', 64)->unique();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('role', 32)->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('acting_for_creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 16);
            $table->text('body');
            $table->string('provider_message_id')->nullable();
            $table->string('email_message_id')->nullable();
            $table->string('in_reply_to')->nullable();
            $table->text('email_references')->nullable();
            $table->string('delivery_status', 32)->default('stored');
            $table->timestamps();
        });

        Schema::create('contact_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_form_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->json('answers');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('inbound_email_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_event_id')->nullable()->unique();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_status', 32);
            $table->string('from_email')->nullable();
            $table->string('subject')->nullable();
            $table->text('raw_excerpt')->nullable();
            $table->timestamps();
        });

        Schema::create('email_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 16);
            $table->string('status', 32);
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('detail')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('email_events');
        Schema::dropIfExists('inbound_email_events');
        Schema::dropIfExists('contact_form_submissions');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('external_contacts');
    }
};
