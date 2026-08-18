<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_accounts', function (Blueprint $table) {
            // Only ever holds fields the Graph API actually returned.
            $table->json('insights')->nullable()->after('granted_scopes');
            $table->timestamp('insights_synced_at')->nullable()->after('insights');
            $table->text('last_error')->nullable()->after('insights_synced_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('checkout_url')->nullable()->after('provider_payment_id');
            $table->timestamp('confirmed_at')->nullable()->after('checkout_url');
            $table->text('last_provider_detail')->nullable()->after('confirmed_at');
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->foreignId('payout_account_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->text('last_provider_detail')->nullable()->after('provider_payout_id');
            $table->timestamp('processed_at')->nullable()->after('last_provider_detail');
        });

        Schema::table('project_files', function (Blueprint $table) {
            // Which disk the object lives on. MySQL keeps the key, never the bytes.
            $table->string('disk', 32)->default('local')->after('storage_key');
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 512);
            $table->string('platform', 16);
            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'token'], 'device_tokens_user_token_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');

        Schema::table('project_files', function (Blueprint $table) {
            $table->dropColumn('disk');
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_account_id');
            $table->dropColumn(['last_provider_detail', 'processed_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['checkout_url', 'confirmed_at', 'last_provider_detail']);
        });

        Schema::table('instagram_accounts', function (Blueprint $table) {
            $table->dropColumn(['insights', 'insights_synced_at', 'last_error']);
        });
    }
};
