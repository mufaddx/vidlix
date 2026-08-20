<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a member wants to be told about, and on which channel.
 *
 * A row per member per event. Absence means the product default rather than
 * silence: shipping this with no rows must not quietly stop everyone's
 * notifications, the same reasoning as the feature flags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 48);
            $table->boolean('push')->default(true);
            $table->boolean('email')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'event']);
        });

        Schema::table('device_tokens', function (Blueprint $table) {
            // A token the provider has rejected is dead. Recording why, and
            // when, beats deleting it silently and wondering later why a
            // member stopped receiving anything.
            $table->timestamp('failed_at')->nullable()->after('last_seen_at');
            $table->string('failure_reason', 191)->nullable()->after('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropColumn(['failed_at', 'failure_reason']);
        });

        Schema::dropIfExists('notification_preferences');
    }
};
