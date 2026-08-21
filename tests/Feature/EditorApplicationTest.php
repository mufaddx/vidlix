<?php

namespace Tests\Feature;

use App\Models\EditorProfile;
use App\Models\Role;
use App\Models\User;
use App\Services\Profiles\EditorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Applying to be listed as an editor.
 *
 * One rule matters more than the rest: **accepting the terms is not approval,
 * and neither is submitting.** Getting that wrong would put unvetted people in
 * front of brands carrying Vidlix's implicit endorsement, which is a different
 * kind of bug from a broken page.
 */
class EditorApplicationTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): EditorProfile
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $role = Role::query()->firstOrCreate(['slug' => 'editor'], ['name' => 'Editor']);
        $user->roles()->attach($role);

        return EditorProfile::query()->create([
            'user_id' => $user->id,
            'username' => 'editor'.$user->id,
            'display_name' => 'Rahul Sen',
            'application_status' => EditorProfile::DRAFT,
        ]);
    }

    private function reviewer(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $role = Role::query()->firstOrCreate(['slug' => 'operations'], ['name' => 'Operations']);
        $user->roles()->attach($role);

        return $user->fresh();
    }

    private function applications(): EditorApplication
    {
        return app(EditorApplication::class);
    }

    /** @param array<string, mixed> $overrides */
    private function completeInput(array $overrides = []): array
    {
        return array_merge([
            'display_name' => 'Rahul Sen',
            'bio' => 'I cut short-form video.',
            'specializations' => "Short form\nDocumentary",
            'software' => "Premiere Pro\nResolve",
            'services' => "Reel editing\nColour",
            'starting_price_minor' => 500000,
            'availability' => 'Two projects a month',
        ], $overrides);
    }

    /* -------------------------------------------------- nothing is automatic */

    public function test_a_new_editor_profile_is_not_in_the_marketplace(): void
    {
        $profile = $this->editor();

        $this->assertFalse($profile->isApproved());
        $this->assertFalse($profile->isPublished());
    }

    public function test_filling_in_the_form_does_not_submit_it(): void
    {
        $profile = $this->editor();

        $this->applications()->saveDraft($profile, $this->completeInput());

        $this->assertSame(EditorProfile::DRAFT, $profile->fresh()->application_status);
        $this->assertNull($profile->fresh()->submitted_at);
    }

    public function test_accepting_the_terms_does_not_approve_anything(): void
    {
        $profile = $this->editor();

        $this->applications()->acceptTerms($profile);

        // The single most tempting shortcut, and the one that would matter most.
        $this->assertNotNull($profile->fresh()->terms_accepted_at);
        $this->assertFalse($profile->fresh()->isApproved());
        $this->assertFalse($profile->fresh()->isPublished());
    }

    public function test_submitting_does_not_approve_anything_either(): void
    {
        $profile = $this->editor();

        $this->applications()->saveDraft($profile, $this->completeInput());
        $this->applications()->acceptTerms($profile->fresh());
        $this->applications()->submit($profile->fresh());

        $profile = $profile->fresh();

        $this->assertSame(EditorProfile::SUBMITTED, $profile->application_status);
        $this->assertNotNull($profile->submitted_at);
        $this->assertFalse($profile->isPublished());
    }

    /* ------------------------------------------------------------ submitting */

    public function test_an_incomplete_application_says_what_is_missing(): void
    {
        $profile = $this->editor();

        $this->applications()->saveDraft($profile, ['bio' => 'Just a bio.']);

        try {
            $this->applications()->submit($profile->fresh());
            $this->fail('An incomplete application should not submit.');
        } catch (ValidationException $e) {
            $message = $e->validator->errors()->first('application');

            // Naming the fields beats "incomplete", which leaves somebody to
            // hunt for what is wrong.
            $this->assertStringContainsString('software', $message);
            $this->assertStringContainsString('terms', $message);
        }
    }

    public function test_the_terms_are_part_of_being_complete(): void
    {
        $profile = $this->editor();

        $this->applications()->saveDraft($profile, $this->completeInput());

        $this->assertContains('the terms', $profile->fresh()->missingForSubmission());

        $this->applications()->acceptTerms($profile->fresh());

        $this->assertSame([], $profile->fresh()->missingForSubmission());
    }

    public function test_an_application_cannot_be_edited_while_it_is_being_read(): void
    {
        $profile = $this->submitted();

        // Editing underneath a reviewer would mean they decide on something
        // that no longer exists.
        $this->expectException(ValidationException::class);

        $this->applications()->saveDraft($profile->fresh(), $this->completeInput(['bio' => 'Changed']));
    }

    /* -------------------------------------------------------------- deciding */

    private function submitted(): EditorProfile
    {
        $profile = $this->editor();

        $this->applications()->saveDraft($profile, $this->completeInput());
        $this->applications()->acceptTerms($profile->fresh());

        return $this->applications()->submit($profile->fresh());
    }

    public function test_approval_is_the_only_thing_that_makes_an_editor_visible(): void
    {
        $profile = $this->submitted();

        $this->applications()->decide($profile->fresh(), $this->reviewer(), EditorProfile::APPROVED);

        $profile = $profile->fresh();

        $this->assertTrue($profile->isApproved());
        $this->assertTrue($profile->isPublished());
        $this->assertNotNull($profile->reviewed_at);
    }

    public function test_an_approved_editor_appears_at_their_public_address(): void
    {
        $profile = $this->submitted();

        $this->get('/'.$profile->username)->assertNotFound();

        $this->applications()->decide($profile->fresh(), $this->reviewer(), EditorProfile::APPROVED);

        $this->get('/'.$profile->username)->assertOk();
    }

    public function test_a_rejection_needs_a_reason(): void
    {
        $profile = $this->submitted();

        // A rejection somebody cannot act on wastes the next round for both
        // sides.
        $this->expectException(ValidationException::class);

        $this->applications()->decide($profile->fresh(), $this->reviewer(), EditorProfile::REJECTED);
    }

    public function test_asking_for_more_needs_a_reason_too(): void
    {
        $profile = $this->submitted();

        $this->expectException(ValidationException::class);

        $this->applications()->decide($profile->fresh(), $this->reviewer(), EditorProfile::MORE_INFO);
    }

    public function test_asking_for_more_hands_it_back_editable_with_the_note(): void
    {
        $profile = $this->submitted();

        $this->applications()->decide(
            $profile->fresh(),
            $this->reviewer(),
            EditorProfile::MORE_INFO,
            'Add a link to your work.',
        );

        $profile = $profile->fresh();

        $this->assertSame(EditorProfile::MORE_INFO, $profile->application_status);
        $this->assertTrue($profile->isEditable());
        $this->assertSame('Add a link to your work.', $profile->review_note);
        $this->assertFalse($profile->isPublished());
    }

    public function test_resubmitting_clears_the_old_note(): void
    {
        $profile = $this->submitted();

        $this->applications()->decide($profile->fresh(), $this->reviewer(), EditorProfile::MORE_INFO, 'Add a link.');
        $this->applications()->saveDraft($profile->fresh(), $this->completeInput(['portfolio_url' => 'https://vidlix.test/work']));
        $this->applications()->submit($profile->fresh());

        // The old note described the previous version and would read as a fresh
        // complaint about this one.
        $this->assertNull($profile->fresh()->review_note);
    }

    public function test_a_rejection_is_not_final(): void
    {
        $profile = $this->submitted();

        $this->applications()->decide($profile->fresh(), $this->reviewer(), EditorProfile::REJECTED, 'Not enough work shown.');

        // They can change it and try again.
        $this->assertTrue($profile->fresh()->isEditable());
    }

    public function test_suspension_takes_an_approved_editor_out_of_the_marketplace(): void
    {
        $profile = $this->submitted();
        $reviewer = $this->reviewer();

        $this->applications()->decide($profile->fresh(), $reviewer, EditorProfile::APPROVED);
        $this->assertTrue($profile->fresh()->isPublished());

        $this->applications()->decide($profile->fresh(), $reviewer, EditorProfile::SUSPENDED, 'Reported repeatedly.');

        $this->assertFalse($profile->fresh()->isPublished());
        $this->get('/'.$profile->username)->assertNotFound();
    }

    public function test_a_decision_that_is_not_a_decision_is_refused(): void
    {
        $profile = $this->submitted();

        $this->expectException(ValidationException::class);

        $this->applications()->decide($profile->fresh(), $this->reviewer(), 'approved_probably');
    }

    /* --------------------------------------------------------- through HTTP */

    public function test_the_form_saves_without_submitting(): void
    {
        $profile = $this->editor();

        $this->actingAs($profile->user)
            ->post(route('app.editors.apply'), $this->completeInput())
            ->assertRedirect();

        $this->assertSame(EditorProfile::DRAFT, $profile->fresh()->application_status);
    }

    public function test_submitting_through_the_app_needs_a_complete_application(): void
    {
        $profile = $this->editor();

        $this->actingAs($profile->user)
            ->post(route('app.editors.submit'))
            ->assertSessionHasErrors('application');

        $this->assertSame(EditorProfile::DRAFT, $profile->fresh()->application_status);
    }
}
