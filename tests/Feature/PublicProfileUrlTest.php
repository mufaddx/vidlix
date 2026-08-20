<?php

namespace Tests\Feature;

use App\Models\CreatorProfile;
use App\Models\EditorProfile;
use App\Models\User;
use App\Models\Username;
use App\Services\Creator\CreatorOnboardingService;
use App\Services\Identity\UsernameRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * vidlix.in/{username}.
 *
 * The URL carries no role, which is only safe if a username means exactly one
 * person across creators and editors alike. Most of what is checked here is
 * that second part.
 */
class PublicProfileUrlTest extends TestCase
{
    use RefreshDatabase;

    private function registry(): UsernameRegistry
    {
        return app(UsernameRegistry::class);
    }

    private function approvedEditor(string $username): EditorProfile
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        return EditorProfile::query()->create([
            'user_id' => $user->id,
            'username' => $username,
            'display_name' => 'Editor '.$username,
            'application_status' => 'approved',
            'visibility' => 'public',
            'accepts_inquiries' => true,
        ]);
    }

    private function publishedCreator(string $username): CreatorProfile
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $profile = app(CreatorOnboardingService::class)->provision($user->id, 'Creator '.$username);

        $profile->update(['username' => $username, 'visibility' => 'public']);
        $profile->publicPage->update([
            'status' => 'published',
            'published_payload' => ['hero_title' => 'Hello', 'cta_text' => 'Work with me'],
        ]);

        return $profile->fresh();
    }

    /* ---------------------------------------------------------------- names */

    public function test_a_creator_and_an_editor_cannot_hold_the_same_username(): void
    {
        $this->approvedEditor('asif');

        // The whole point of the registry. Before it existed both of these
        // could be saved, and vidlix.in/asif would have meant whichever row the
        // query happened to return first.
        $this->expectException(ValidationException::class);

        $user = User::factory()->create();
        CreatorProfile::query()->create([
            'user_id' => $user->id,
            'username' => 'asif',
            'display_name' => 'Asif',
            'visibility' => 'private',
        ]);
    }

    public function test_confusable_separators_are_treated_as_the_same_name(): void
    {
        $this->approvedEditor('john-doe');

        $registry = $this->registry();

        $this->assertFalse($registry->isAvailable('john.doe'));
        $this->assertFalse($registry->isAvailable('john_doe'));
        $this->assertFalse($registry->isAvailable('JOHN-DOE'));
    }

    public function test_reserved_paths_cannot_be_claimed(): void
    {
        $registry = $this->registry();

        foreach (['admin', 'login', 'settings', 'api', 'autodm', 'terms', 'privacy', 'support'] as $reserved) {
            $this->assertTrue($registry->isReserved($reserved), $reserved.' should be reserved');
            $this->assertFalse($registry->isAvailable($reserved), $reserved.' should not be claimable');
        }
    }

    public function test_a_reserved_path_is_checked_after_normalising(): void
    {
        // "Admin" is not in the reserved list; "admin" is. Comparing before
        // normalising would let the capitalised form straight through.
        $this->assertTrue($this->registry()->isReserved('Admin'));
        $this->assertFalse($this->registry()->isAvailable('ADMIN'));
    }

    public function test_malformed_usernames_are_refused(): void
    {
        $registry = $this->registry();

        foreach (['ab', str_repeat('a', 31), '-lead', 'trail-', 'has space', 'emoji🙂', 'ünicode'] as $bad) {
            $this->assertFalse($registry->isWellFormed($bad), $bad.' should be malformed');
        }

        $this->assertTrue($registry->isWellFormed('asif'));
        $this->assertTrue($registry->isWellFormed('sharp.cut_99'));
    }

    /* ------------------------------------------------------------- resolving */

    public function test_a_creator_profile_resolves_at_the_bare_username(): void
    {
        $this->publishedCreator('mira');

        $this->get('/mira')->assertOk();
    }

    public function test_an_editor_profile_resolves_at_the_bare_username(): void
    {
        $this->approvedEditor('sharpcut');

        $this->get('/sharpcut')->assertOk();
    }

    public function test_no_role_prefix_appears_in_the_public_url(): void
    {
        $editor = $this->approvedEditor('rahul');

        $this->get('/rahul')
            ->assertOk()
            // The role may be described on the page; it must not be in the link
            // people are told to share.
            ->assertDontSee('/editor/rahul', false)
            ->assertDontSee('/editors/rahul', false)
            ->assertDontSee('/u/rahul', false);

        $_ = $editor;
    }

    public function test_the_old_prefixed_addresses_redirect_rather_than_break(): void
    {
        $this->publishedCreator('mira');
        $this->approvedEditor('sharpcut');

        $this->get('/u/mira')->assertRedirect('/mira');
        $this->get('/editors/sharpcut')->assertRedirect('/sharpcut');
        $this->get('/editor/sharpcut')->assertRedirect('/sharpcut');
    }

    public function test_a_capitalised_handle_redirects_to_the_canonical_one(): void
    {
        $this->publishedCreator('mira');

        $this->get('/Mira')->assertRedirect('/mira');
    }

    public function test_an_unknown_username_is_a_plain_404(): void
    {
        $this->get('/nobody-here')->assertNotFound();
    }

    /* ------------------------------------------------------------ visibility */

    public function test_a_private_creator_page_is_indistinguishable_from_a_missing_one(): void
    {
        $profile = $this->publishedCreator('hidden');
        $profile->update(['visibility' => 'private']);

        // Same status as an unknown name on purpose. "This account exists but
        // is hidden" still confirms the account exists.
        $this->get('/hidden')->assertNotFound();
    }

    public function test_an_unapproved_editor_is_not_public(): void
    {
        $editor = $this->approvedEditor('pending');
        $editor->update(['application_status' => 'submitted']);

        $this->get('/pending')->assertNotFound();
    }

    public function test_a_suspended_editor_is_not_public(): void
    {
        $editor = $this->approvedEditor('gone');
        $editor->update(['application_status' => 'suspended']);

        $this->get('/gone')->assertNotFound();
    }

    /* -------------------------------------------------------------- renaming */

    public function test_a_rename_retires_the_old_handle_and_redirects_it(): void
    {
        $profile = $this->publishedCreator('oldname');

        $profile->update(['username' => 'newname']);

        $this->assertDatabaseHas('usernames', ['username' => 'oldname', 'status' => Username::RETIRED]);
        $this->assertDatabaseHas('usernames', ['username' => 'newname', 'status' => Username::ACTIVE]);

        // The old link is printed on things nobody can recall, so it follows
        // the person rather than dying.
        $this->get('/oldname')->assertRedirect('/newname');
    }

    public function test_a_retired_handle_is_not_handed_to_somebody_else(): void
    {
        $profile = $this->publishedCreator('desirable');
        $profile->update(['username' => 'somethingelse']);

        $this->assertFalse($this->registry()->isAvailable('desirable'));
    }

    /* ------------------------------------------------------------- suggesting */

    public function test_a_suggested_handle_avoids_names_already_taken(): void
    {
        $this->approvedEditor('taken-name');

        $suggestion = $this->registry()->suggestFrom('Taken Name');

        $this->assertNotSame('taken-name', $suggestion);
        $this->assertTrue($this->registry()->isAvailable($suggestion));
    }

    public function test_a_suggested_handle_never_lands_on_a_reserved_word(): void
    {
        $suggestion = $this->registry()->suggestFrom('Admin');

        $this->assertFalse($this->registry()->isReserved($suggestion));
    }
}
