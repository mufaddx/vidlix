<?php

namespace Tests\Feature;

use App\Models\BrandDocument;
use App\Models\BrandProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The business details and documents a brand must produce before an operator
 * can verify it.
 */
class BrandVerificationDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function brand(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $role = Role::query()->firstOrCreate(['slug' => 'brand'], ['name' => 'Brand']);
        $user->roles()->syncWithoutDetaching([$role->id]);

        BrandProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Abc Foods',
            'slug' => 'abc-foods-'.$user->id,
            'verification_status' => 'unverified',
        ]);

        return $user->fresh();
    }

    public function test_a_brand_can_save_its_tax_address_and_authorised_person(): void
    {
        $user = $this->brand();

        $this->actingAs($user)->post(route('app.brand.save'), [
            'company_name' => 'Abc Foods',
            'legal_name' => 'Abc Foods Private Limited',
            'gstin' => '27aaaaa0000a1z5',
            'pan' => 'AAAAA0000A',
            'registered_address' => '14 Marine Drive, Mumbai',
            'authorized_person_name' => 'Rahul Sharma',
            'authorized_person_designation' => 'Director',
            'authorized_person_email' => 'rahul@abcfoods.test',
        ])->assertRedirect();

        $profile = BrandProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Abc Foods Private Limited', $profile->legal_name);
        // Stored upper-cased, so the same registration is not filed two ways.
        $this->assertSame('27AAAAA0000A1Z5', $profile->gstin);
        $this->assertSame('Rahul Sharma', $profile->authorized_person_name);
    }

    public function test_a_malformed_gstin_is_refused(): void
    {
        $user = $this->brand();

        $this->from(route('app.brand'))
            ->actingAs($user)
            ->post(route('app.brand.save'), [
                'company_name' => 'Abc Foods',
                'gstin' => 'not-a-gstin',
            ])
            ->assertSessionHasErrors('gstin');
    }

    public function test_filling_the_form_does_not_verify_the_brand(): void
    {
        $user = $this->brand();

        $this->actingAs($user)->post(route('app.brand.save'), [
            'company_name' => 'Abc Foods',
            'legal_name' => 'Abc Foods Private Limited',
            'gstin' => '27AAAAA0000A1Z5',
            'pan' => 'AAAAA0000A',
            'registered_address' => '14 Marine Drive, Mumbai',
            'authorized_person_name' => 'Rahul Sharma',
            'authorized_person_email' => 'rahul@abcfoods.test',
        ]);

        // Only an operator decision verifies a brand. Self-declared details
        // move it to review and no further.
        $this->assertSame('pending_review', BrandProfile::query()->where('user_id', $user->id)->firstOrFail()->verification_status);
    }

    public function test_a_document_upload_stores_the_key_and_stays_pending(): void
    {
        Storage::fake('local');
        $user = $this->brand();

        $this->actingAs($user)->post(route('app.brand.documents.store'), [
            'kind' => 'gst_certificate',
            'document' => UploadedFile::fake()->create('gst.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $document = BrandDocument::query()->firstOrFail();
        $this->assertSame('gst_certificate', $document->kind);
        $this->assertSame('pending', $document->review_status);
        $this->assertNotEmpty($document->storage_key);
        Storage::disk($document->disk)->assertExists($document->storage_key);
    }

    public function test_an_approved_document_cannot_be_removed_by_the_brand(): void
    {
        Storage::fake('local');
        $user = $this->brand();
        $profile = BrandProfile::query()->where('user_id', $user->id)->firstOrFail();

        $document = BrandDocument::query()->create([
            'brand_profile_id' => $profile->id,
            'kind' => 'gst_certificate',
            'original_name' => 'gst.pdf',
            'disk' => 'local',
            'storage_key' => 'brand-documents/'.$profile->id.'/gst.pdf',
            'review_status' => 'approved',
        ]);

        $this->actingAs($user)
            ->delete(route('app.brand.documents.destroy', $document))
            ->assertForbidden();

        $this->assertDatabaseHas('brand_documents', ['id' => $document->id]);
    }

    public function test_a_brand_cannot_remove_another_brands_document(): void
    {
        Storage::fake('local');
        $mine = $this->brand();
        $theirs = $this->brand();

        $document = BrandDocument::query()->create([
            'brand_profile_id' => BrandProfile::query()->where('user_id', $theirs->id)->firstOrFail()->id,
            'kind' => 'pan_card',
            'original_name' => 'pan.pdf',
            'disk' => 'local',
            'storage_key' => 'brand-documents/x/pan.pdf',
        ]);

        $this->actingAs($mine)
            ->delete(route('app.brand.documents.destroy', $document))
            ->assertNotFound();
    }

    public function test_the_page_says_what_verification_is_still_missing(): void
    {
        $user = $this->brand();

        $this->actingAs($user)->get(route('app.brand'))
            ->assertOk()
            ->assertSee('Verification still needs')
            ->assertSee('GSTIN');
    }
}
