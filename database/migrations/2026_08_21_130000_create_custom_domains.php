<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bringing your own hostname to a public contact form.
 *
 * The hostname is stored normalised and unique, because that unique index is
 * the only thing that actually prevents two tenants claiming one name — the
 * check in the service can be raced, the index cannot.
 *
 * Nothing about a domain is trusted until it is verified. The status column is
 * a state machine rather than a boolean for exactly that reason: "the DNS
 * points at us" and "we hold a certificate for it" are different facts, and a
 * domain is only served once both are true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_domains', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Which of their profiles the hostname serves. A person may bring a
            // domain for their creator page and another for their editor one.
            $table->string('owner_scope', 16);

            // Lowercased, punycode, no trailing dot. Comparison and routing both
            // use this value, so it must be the only form ever stored.
            $table->string('hostname', 253)->unique();

            /*
             | not_connected → pending_verification → dns_required
             |   → ownership_pending → ssl_provisioning → active
             | with failed, suspended and disconnected as exits.
             */
            $table->string('status', 32)->default('pending_verification');

            // The random value the owner publishes to prove the domain is
            // theirs. Regenerated on reconnect so an old record cannot be
            // replayed by whoever holds the domain next.
            $table->string('verification_token', 64);
            $table->string('dns_target')->nullable();

            $table->timestamp('dns_verified_at')->nullable();
            $table->timestamp('ownership_verified_at')->nullable();
            $table->timestamp('ssl_issued_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();

            // Set when the provider is asked to take the hostname on, so a
            // provider-side record can be found again to tear it down.
            $table->string('provider')->nullable();
            $table->string('provider_hostname_id')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'owner_scope']);
            $table->index(['status']);
        });

        /*
         | Every state change, kept. A domain that stopped working is almost
         | always a story about what changed and when, and without this the
         | only record is a status column that has already moved on.
         */
        Schema::create('custom_domain_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_domain_id')->constrained()->cascadeOnDelete();
            $table->string('event', 48);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('detail')->nullable();
            $table->timestamps();

            $table->index(['custom_domain_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_domain_events');
        Schema::dropIfExists('custom_domains');
    }
};
