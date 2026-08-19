<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Switches an operator can throw without a deploy.
 *
 * Two kinds live here. A feature flag turns one capability on or off. The
 * maintenance switch closes the whole site to members while leaving staff in,
 * which is the difference between this and Laravel's own maintenance mode:
 * `artisan down` locks everyone out including the people who need to fix
 * things, and needs shell access this host makes awkward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false);
            // A flag can be opened to staff first, then to everybody.
            $table->string('audience', 16)->default('everyone');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // system_settings already exists from the CMS/commerce migration; it
        // simply never recorded who last changed a value.
        Schema::table('system_settings', function (Blueprint $table) {
            $table->foreignId('updated_by_user_id')->nullable()->after('value')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by_user_id');
        });

        Schema::dropIfExists('feature_flags');
    }
};
