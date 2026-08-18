<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('body');
            $table->string('status', 32)->default('published');
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
        });

        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('author_name');
            $table->string('author_role')->nullable();
            $table->text('quote');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('featured_creators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('management_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('price_minor')->default(0);
            $table->string('currency', 8)->default('INR');
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('creator_manager_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('invited');
            $table->json('permissions')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            // Named explicitly: the implicit name is 68 characters, over MySQL's
            // 64-character identifier limit.
            $table->unique(['creator_user_id', 'manager_user_id'], 'creator_manager_pair_unique');
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('status', 32)->default('draft');
            $table->string('objective')->nullable();
            $table->text('brief')->nullable();
            $table->unsignedInteger('budget_minor')->nullable();
            $table->timestamp('application_deadline')->nullable();
            $table->timestamp('delivery_deadline')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 32)->default('draft');
            $table->unsignedInteger('total_amount_minor')->nullable();
            $table->unsignedInteger('advance_amount_minor')->nullable();
            $table->date('deadline')->nullable();
            $table->unsignedInteger('revision_limit')->default(2);
            $table->timestamps();
        });

        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('currency', 8)->default('INR');
            $table->timestamps();
            $table->unique(['user_id', 'kind', 'currency']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_account_id')->constrained()->cascadeOnDelete();
            $table->string('entry_uuid')->unique();
            $table->string('state', 32);
            $table->integer('amount_minor');
            $table->string('currency', 8)->default('INR');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('provider_reference')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_uuid')->unique();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('amount_minor');
            $table->string('currency', 8)->default('INR');
            $table->string('provider')->nullable();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->foreignId('payer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('payable');
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('creator_manager_relationships');
        Schema::dropIfExists('management_plans');
        Schema::dropIfExists('featured_creators');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('homepage_sections');
        Schema::dropIfExists('cms_pages');
    }
};
