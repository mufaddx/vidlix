<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Services\Taxonomy\CategoryService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RolesAndCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function plainUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_signup_picks_one_starting_role_and_more_are_added_later(): void
    {
        // Sign-up asks what you do so the right terms can be shown, but it is a
        // starting point rather than a permanent choice — /roles adds the rest.
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->assertSame([], $user->roleSlugs());

        $this->actingAs($user)->post(route('app.roles.apply'), ['role' => 'creator'])->assertRedirect();

        $this->assertSame(['creator'], $user->fresh()->roleSlugs());
    }

    public function test_a_person_can_hold_both_creator_and_editor(): void
    {
        $user = $this->plainUser();

        $this->actingAs($user)->post(route('app.roles.apply'), ['role' => 'creator'])->assertRedirect();
        $this->actingAs($user)->post(route('app.roles.apply'), ['role' => 'editor'])->assertRedirect();

        $roles = $user->fresh()->roleSlugs();
        $this->assertContains('creator', $roles);
        $this->assertContains('editor', $roles);
        $this->assertNotNull($user->fresh()->creatorProfile);
        $this->assertNotNull($user->fresh()->editorProfile);
    }

    public function test_manager_cannot_be_applied_for(): void
    {
        $user = $this->plainUser();

        $this->actingAs($user)
            ->post(route('app.roles.apply'), ['role' => 'manager'])
            ->assertSessionHasErrors('role');

        $this->assertNotContains('manager', $user->fresh()->roleSlugs());
    }

    public function test_a_creator_may_choose_at_most_three_categories(): void
    {
        $user = $this->plainUser();
        $this->actingAs($user)->post(route('app.roles.apply'), ['role' => 'creator']);
        $profile = $user->fresh()->creatorProfile;

        $four = Category::query()->ofType('creator')->limit(4)->pluck('id')->all();

        $this->expectException(ValidationException::class);
        app(CategoryService::class)->sync($profile, 'creator', $four, [], $user, Category::MAX_PER_CREATOR);
    }

    public function test_three_categories_are_saved_and_readable_back(): void
    {
        $user = $this->plainUser();
        $this->actingAs($user)->post(route('app.roles.apply'), ['role' => 'creator']);
        $profile = $user->fresh()->creatorProfile;

        $three = Category::query()->ofType('creator')->limit(3)->pluck('id')->all();

        $this->actingAs($user)
            ->post(route('app.roles.creator-categories'), ['category_ids' => $three])
            ->assertRedirect();

        $saved = app(CategoryService::class)->forProfile($profile);
        $this->assertCount(3, $saved);
    }

    public function test_saving_categories_replaces_rather_than_appends(): void
    {
        $user = $this->plainUser();
        $this->actingAs($user)->post(route('app.roles.apply'), ['role' => 'creator']);
        $profile = $user->fresh()->creatorProfile;
        $service = app(CategoryService::class);

        $ids = Category::query()->ofType('creator')->limit(4)->pluck('id')->all();

        $service->sync($profile, 'creator', array_slice($ids, 0, 3), [], $user, 3);
        $service->sync($profile, 'creator', array_slice($ids, 3, 1), [], $user, 3);

        $saved = $service->forProfile($profile);
        $this->assertCount(1, $saved);
        $this->assertSame($ids[3], $saved->first()->id);
    }

    public function test_a_proposed_category_works_immediately_but_stays_out_of_the_public_list(): void
    {
        $user = $this->plainUser();
        $this->actingAs($user)->post(route('app.roles.apply'), ['role' => 'creator']);
        $profile = $user->fresh()->creatorProfile;
        $service = app(CategoryService::class);

        $service->sync($profile, 'creator', [], ['Street Food Reviews'], $user, 3);

        $proposed = Category::query()->where('slug', 'street-food-reviews')->firstOrFail();
        $this->assertSame('pending', $proposed->status);

        // Usable by the person who proposed it...
        $this->assertTrue($service->forProfile($profile)->contains('id', $proposed->id));
        $this->assertTrue($service->selectable('creator', $profile)->contains('id', $proposed->id));

        // ...but not offered to everybody else until an admin approves it.
        $other = $this->plainUser();
        $this->actingAs($other)->post(route('app.roles.apply'), ['role' => 'creator']);
        $this->assertFalse($service->selectable('creator', $other->fresh()->creatorProfile)->contains('id', $proposed->id));

        $service->approve($proposed);
        $this->assertTrue($service->selectable('creator', $other->fresh()->creatorProfile)->contains('id', $proposed->id));
    }

    public function test_category_names_that_differ_only_in_spacing_or_case_are_the_same_category(): void
    {
        $service = app(CategoryService::class);
        $user = $this->plainUser();

        $a = $service->findOrPropose('creator', 'Short Form', $user);
        $b = $service->findOrPropose('creator', '  short   form  ', $user);

        // This is the whole reason categories are rows and not free text.
        $this->assertSame($a->id, $b->id);
    }

    public function test_the_roles_page_does_not_offer_manager(): void
    {
        $this->actingAs($this->plainUser())
            ->get(route('app.roles'))
            ->assertOk()
            ->assertSee('Apply as creator', false)
            ->assertDontSee('Apply as manager', false);
    }

    public function test_editor_and_brand_taxonomies_are_separate_from_creator(): void
    {
        $service = app(CategoryService::class);

        $creatorSlugs = $service->selectable('creator')->pluck('slug');
        $editorSlugs = $service->selectable('editor')->pluck('slug');

        $this->assertTrue($editorSlugs->contains('documentary'));
        $this->assertFalse($creatorSlugs->contains('documentary'));
        $this->assertTrue($service->selectable('brand')->contains('slug', 'fmcg'));
    }
}
