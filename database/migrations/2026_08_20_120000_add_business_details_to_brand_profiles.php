<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The business details a brand has to give before it can be verified: tax
 * registration, registered address, and a named person who is authorised to
 * sign for the company.
 *
 * All nullable, because a brand fills them in over time and the profile row
 * exists from the moment the role is granted. Verification is what checks they
 * are present, not the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_profiles', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('company_name');
            $table->string('gstin', 20)->nullable()->after('industry');
            $table->string('pan', 12)->nullable()->after('gstin');
            $table->string('cin', 25)->nullable()->after('pan');
            $table->text('registered_address')->nullable()->after('cin');
            $table->string('billing_state', 64)->nullable()->after('registered_address');
            $table->string('billing_country', 64)->nullable()->after('billing_state');
            $table->string('billing_pincode', 12)->nullable()->after('billing_country');

            $table->string('authorized_person_name')->nullable()->after('billing_pincode');
            $table->string('authorized_person_designation')->nullable()->after('authorized_person_name');
            $table->string('authorized_person_email')->nullable()->after('authorized_person_designation');
            $table->string('authorized_person_phone', 24)->nullable()->after('authorized_person_email');
        });

        Schema::create('brand_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_profile_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('original_name');
            // The file itself lives in object storage; MySQL keeps the key only.
            $table->string('disk', 32);
            $table->string('storage_key');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime', 128)->nullable();
            $table->string('review_status', 32)->default('pending');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['brand_profile_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_documents');

        Schema::table('brand_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name', 'gstin', 'pan', 'cin', 'registered_address',
                'billing_state', 'billing_country', 'billing_pincode',
                'authorized_person_name', 'authorized_person_designation',
                'authorized_person_email', 'authorized_person_phone',
            ]);
        });
    }
};
