<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('platform')->nullable();
            $table->unsignedInteger('creator_count')->nullable();
            $table->string('location')->nullable();
            $table->string('language')->nullable();
            $table->text('usage_rights')->nullable();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('counterparty_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('revisions_used')->default(0);
        });

        Schema::create('portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->string('storage_key')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('campaign_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_profile_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('applied');
            $table->unsignedInteger('proposed_fee_minor')->nullable();
            $table->text('message')->nullable();
            $table->string('availability')->nullable();
            $table->json('analytics_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'creator_profile_id']);
        });

        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->string('proposal_uuid')->unique();
            $table->nullableMorphs('proposible');
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('sent');
            $table->timestamps();
        });

        Schema::create('proposal_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->unsignedInteger('amount_minor');
            $table->string('currency', 8)->default('INR');
            $table->json('deliverables')->nullable();
            $table->date('deadline')->nullable();
            $table->unsignedInteger('revisions')->nullable();
            $table->text('usage_rights')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['proposal_id', 'version_number']);
        });

        Schema::create('agreements', function (Blueprint $table) {
            $table->id();
            $table->string('agreement_uuid')->unique();
            $table->nullableMorphs('agreeable');
            $table->unsignedInteger('version_number')->default(1);
            $table->json('terms');
            $table->string('status', 32)->default('draft');
            $table->timestamps();
        });

        Schema::create('agreement_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('typed_name');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('seller_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('buyer_user_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('invoiceable');
            $table->unsignedInteger('subtotal_minor')->default(0);
            $table->unsignedInteger('tax_minor')->default(0);
            $table->unsignedInteger('fee_minor')->default(0);
            $table->unsignedInteger('total_minor')->default(0);
            $table->string('currency', 8)->default('INR');
            $table->string('status', 32)->default('issued');
            $table->date('due_date')->nullable();
            $table->string('pdf_storage_key')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('amount_minor');
            $table->timestamps();
        });

        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('original_name');
            $table->string('storage_key');
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->boolean('watermarked')->default(false);
            $table->timestamps();
        });

        Schema::create('project_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('feedback')->nullable();
            $table->foreignId('project_file_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider_beneficiary_ref')->nullable();
            $table->string('masked_account')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_minor');
            $table->string('currency', 8)->default('INR');
            $table->string('status', 32)->default('requested');
            $table->string('provider_payout_id')->nullable();
            $table->timestamps();
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->string('dispute_uuid')->unique();
            $table->nullableMorphs('disputable');
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 64);
            $table->text('statement');
            $table->string('status', 32)->default('open');
            $table->text('resolution')->nullable();
            $table->timestamps();
        });

        Schema::create('dispute_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewee_user_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('reviewable');
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->timestamps();
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

        Schema::create('manager_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('management_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('management_plan_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 32)->default('disabled');
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->text('detail')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 64);
            $table->string('priority', 16)->default('normal');
            $table->string('status', 32)->default('open');
            $table->string('subject');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('body');
            $table->string('status', 32)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->unsignedInteger('bps')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automations');
        Schema::dropIfExists('management_subscriptions');
        Schema::dropIfExists('manager_activity_logs');
        Schema::dropIfExists('manager_invitations');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('dispute_evidence');
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('payout_accounts');
        Schema::dropIfExists('project_revisions');
        Schema::dropIfExists('project_files');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('agreement_acceptances');
        Schema::dropIfExists('agreements');
        Schema::dropIfExists('proposal_versions');
        Schema::dropIfExists('proposals');
        Schema::dropIfExists('campaign_applications');
        Schema::dropIfExists('portfolio_items');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropConstrainedForeignId('counterparty_user_id');
            $table->dropConstrainedForeignId('conversation_id');
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn('revisions_used');
        });
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['platform', 'creator_count', 'location', 'language', 'usage_rights']);
        });
    }
};
