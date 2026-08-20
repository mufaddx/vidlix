<?php

use App\Support\DefaultContactFormSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A contact form belongs to a person, not to a creator page.
 *
 * contact_forms hung off creator_public_pages, which is why editors had a
 * fixed four-field enquiry box and a whole second service to render it. There
 * was nothing creator-shaped about a contact form; it was just where the table
 * happened to be attached.
 *
 * So the owner moves onto the form itself, matching how conversations already
 * name an owner and a scope. creator_public_page_id stays and is still written
 * by creator pages — dropping it would rewrite working queries for no benefit —
 * but it is nullable now, because an editor has no such page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_forms', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            $table->string('owner_scope', 16)->nullable()->after('owner_user_id');
            $table->boolean('is_enabled')->default(true)->after('current_version');

            $table->index(['owner_user_id', 'owner_scope']);
        });

        // SQLite cannot drop a unique index by altering the column, and the
        // constraint has to go either way: one user may now hold a creator form
        // and an editor form at once.
        $this->relaxCreatorPageUniqueness();

        $this->backfillCreatorOwners();
        $this->createEditorForms();
    }

    public function down(): void
    {
        DB::table('contact_forms')->whereNull('creator_public_page_id')->delete();

        Schema::table('contact_forms', function (Blueprint $table) {
            $table->dropIndex(['owner_user_id', 'owner_scope']);
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn(['owner_scope', 'is_enabled']);
        });
    }

    private function relaxCreatorPageUniqueness(): void
    {
        // A unique index on a nullable column still permits many nulls, so the
        // creator side keeps its guarantee and editors are simply not covered
        // by it. Only the not-null constraint has to be lifted.
        if (DB::getDriverName() === 'sqlite') {
            // SQLite rebuilds the table for this; doctrine/dbal is not
            // installed, so it is done by hand.
            DB::statement('PRAGMA foreign_keys = OFF');
            Schema::table('contact_forms', function (Blueprint $table) {
                $table->unsignedBigInteger('creator_public_page_id')->nullable()->change();
            });
            DB::statement('PRAGMA foreign_keys = ON');

            return;
        }

        Schema::table('contact_forms', function (Blueprint $table) {
            $table->unsignedBigInteger('creator_public_page_id')->nullable()->change();
        });
    }

    private function backfillCreatorOwners(): void
    {
        $forms = DB::table('contact_forms')
            ->join('creator_public_pages', 'creator_public_pages.id', '=', 'contact_forms.creator_public_page_id')
            ->join('creator_profiles', 'creator_profiles.id', '=', 'creator_public_pages.creator_profile_id')
            ->select('contact_forms.id', 'creator_profiles.user_id')
            ->get();

        foreach ($forms as $form) {
            DB::table('contact_forms')->where('id', $form->id)->update([
                'owner_user_id' => $form->user_id,
                'owner_scope' => 'creator',
            ]);
        }
    }

    /**
     * Every existing editor gets the same starting form a creator gets, so the
     * builder has something to open rather than an empty state that has to be
     * explained.
     */
    private function createEditorForms(): void
    {
        $editors = DB::table('editor_profiles')->select('id', 'user_id')->get();
        $now = now();

        foreach ($editors as $editor) {
            $exists = DB::table('contact_forms')
                ->where('owner_user_id', $editor->user_id)
                ->where('owner_scope', 'editor')
                ->exists();

            if ($exists) {
                continue;
            }

            $formId = DB::table('contact_forms')->insertGetId([
                'owner_user_id' => $editor->user_id,
                'owner_scope' => 'editor',
                'creator_public_page_id' => null,
                'current_version' => 1,
                'is_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('contact_form_versions')->insert([
                'contact_form_id' => $formId,
                'version_number' => 1,
                'schema_json' => json_encode(DefaultContactFormSchema::forEditor()),
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
