<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Managers were creator-only. A manager is now appointed over any account —
 * creator, brand or editor — and may be appointed by the account owner or
 * provided by Vidlix itself.
 *
 * The old tables carried no rows, so they are replaced outright rather than
 * migrated column by column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('creator_manager_relationships');
        Schema::dropIfExists('manager_invitations');

        Schema::create('manager_assignments', function (Blueprint $table) {
            $table->id();
            // Whose account is being managed, and which side of it.
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope', 16); // creator | brand | editor
            $table->string('status', 32)->default('active'); // active | revoked
            // "owner" = the account holder appointed them.
            // "company" = Vidlix provided the manager; the UI must say so.
            $table->string('source', 16)->default('owner');
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('permissions')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['owner_user_id', 'manager_user_id', 'scope'], 'manager_assignment_unique');
            $table->index(['manager_user_id', 'status']);
        });

        Schema::create('manager_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope', 16);
            $table->string('email');
            $table->string('mobile')->nullable();
            $table->string('name')->nullable();
            $table->string('token')->unique();
            $table->string('source', 16)->default('owner');
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('permissions')->nullable();
            $table->string('status', 32)->default('invited'); // invited | accepted | revoked | expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'status']);
        });

        Schema::table('manager_activity_logs', function (Blueprint $table) {
            $table->string('scope', 16)->default('creator')->after('manager_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('manager_activity_logs', function (Blueprint $table) {
            $table->dropColumn('scope');
        });

        Schema::dropIfExists('manager_invitations');
        Schema::dropIfExists('manager_assignments');

        Schema::create('creator_manager_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('invited');
            $table->json('permissions')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['creator_user_id', 'manager_user_id'], 'creator_manager_pair_unique');
        });

        Schema::create('manager_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('mobile')->nullable();
            $table->string('token')->unique();
            $table->json('permissions')->nullable();
            $table->string('status', 32)->default('invited');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }
};
