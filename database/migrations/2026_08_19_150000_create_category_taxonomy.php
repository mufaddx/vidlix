<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared taxonomy for creators, editors and brands.
 *
 * Free text could not be filtered — "Short Form", "shortform" and "short-form"
 * are three different strings and a brand searching for one finds none of the
 * others. Categories are rows, so search and filtering work, while anyone may
 * still propose a new one: it is usable immediately and becomes publicly
 * listable once an admin approves it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16); // creator | editor | brand
            $table->string('name');
            $table->string('slug');
            $table->string('status', 16)->default('approved'); // approved | pending | rejected
            $table->foreignId('proposed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['type', 'slug']);
            $table->index(['type', 'status']);
        });

        Schema::create('category_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->morphs('categorizable'); // creator / editor / brand profile
            $table->timestamps();

            $table->unique(['category_id', 'categorizable_type', 'categorizable_id'], 'category_assignment_unique');
        });

        Schema::table('creator_profiles', function (Blueprint $table) {
            // Denormalised so brand search can filter without a Graph call.
            // Only ever written from a real Instagram sync; null means unknown,
            // never zero.
            $table->unsignedBigInteger('follower_count')->nullable()->after('instagram_connection_status');
            $table->timestamp('follower_count_synced_at')->nullable()->after('follower_count');
        });
    }

    public function down(): void
    {
        Schema::table('creator_profiles', function (Blueprint $table) {
            $table->dropColumn(['follower_count', 'follower_count_synced_at']);
        });

        Schema::dropIfExists('category_assignments');
        Schema::dropIfExists('categories');
    }
};
