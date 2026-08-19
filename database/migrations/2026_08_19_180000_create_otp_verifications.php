<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time codes for sign-up and password reset.
 *
 * The code itself is never stored — only a hash — so a database read cannot
 * hand somebody a working code. Every row expires, is single-use, and counts
 * its own failed attempts, because an unlimited guess budget makes a six-digit
 * code worthless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            // Email address or mobile number the code was sent to.
            $table->string('identifier');
            $table->string('channel', 16)->default('email'); // email | sms
            $table->string('purpose', 32);                   // signup | password_reset
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['identifier', 'purpose', 'consumed_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
