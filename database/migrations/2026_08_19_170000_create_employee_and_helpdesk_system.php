<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff accounts with explicit abilities, and the help desk they answer.
 *
 * Admin access used to be a single gate that six different role slugs opened,
 * so anyone who could edit CMS copy could also approve a payout. Abilities are
 * now granted one at a time, per employee, and every admin route names the one
 * it needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_code')->unique();
            $table->string('title')->nullable();
            $table->string('status', 16)->default('active'); // active | suspended
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('employee_abilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('ability', 64);
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'ability']);
        });

        // Help desk threads live in the same conversation family as everything
        // else, so replies, delivery status and inbound routing all reuse the
        // machinery that already exists.
        Schema::create('support_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('open'); // open | pending | closed
            $table->string('priority', 16)->default('normal');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_threads');
        Schema::dropIfExists('employee_abilities');
        Schema::dropIfExists('employees');
    }
};
