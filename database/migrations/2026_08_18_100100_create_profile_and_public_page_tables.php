<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creator_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('username')->unique();
            $table->string('display_name');
            $table->text('bio')->nullable();
            $table->json('niches')->nullable();
            $table->string('visibility', 32)->default('private');
            $table->string('onboarding_step', 64)->default('welcome');
            $table->unsignedTinyInteger('profile_completion')->default(0);
            $table->string('instagram_connection_status', 32)->default('disconnected');
            $table->timestamps();
        });

        Schema::create('editor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('username')->unique();
            $table->string('display_name');
            $table->text('bio')->nullable();
            $table->string('application_status', 32)->default('not_applied');
            $table->json('software')->nullable();
            $table->json('specializations')->nullable();
            $table->unsignedInteger('starting_price_minor')->nullable();
            $table->string('availability', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('brand_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('slug')->unique();
            $table->string('website')->nullable();
            $table->string('business_email')->nullable();
            $table->string('verification_status', 32)->default('unverified');
            $table->string('industry')->nullable();
            $table->timestamps();
        });

        Schema::create('manager_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->timestamps();
        });

        Schema::create('social_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('username_url_template')->nullable();
            $table->boolean('supports_username')->default(true);
            $table->boolean('supports_full_url')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('creator_social_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_platform_id')->constrained()->cascadeOnDelete();
            $table->string('input_mode', 16);
            $table->string('input_value');
            $table->string('resolved_url');
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('creator_public_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('draft_payload');
            $table->json('published_payload')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('theme', 32)->default('professional');
            $table->timestamps();
        });

        Schema::create('contact_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_public_page_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamps();
        });

        Schema::create('contact_form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_form_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('schema_json');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['contact_form_id', 'version_number']);
        });

        Schema::create('instagram_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('disconnected');
            $table->string('ig_user_id')->nullable();
            $table->string('username')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->text('token_encrypted')->nullable();
            $table->json('granted_scopes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_accounts');
        Schema::dropIfExists('contact_form_versions');
        Schema::dropIfExists('contact_forms');
        Schema::dropIfExists('creator_public_pages');
        Schema::dropIfExists('creator_social_links');
        Schema::dropIfExists('social_platforms');
        Schema::dropIfExists('manager_profiles');
        Schema::dropIfExists('brand_profiles');
        Schema::dropIfExists('editor_profiles');
        Schema::dropIfExists('creator_profiles');
    }
};
