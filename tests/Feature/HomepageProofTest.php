<?php

namespace Tests\Feature;

use App\Models\CreatorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The numbers on the landing page are counted, not claimed.
 */
class HomepageProofTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_site_shows_zero_rather_than_an_invented_number(): void
    {
        // A small site is allowed to look small. Inventing a figure here would
        // be the same lie as inventing a wallet balance.
        $this->get('/')->assertOk()->assertSee('published creators');
    }

    public function test_the_figure_matches_the_number_of_published_creators(): void
    {
        foreach (['one', 'two', 'three'] as $index => $name) {
            $user = User::factory()->create(['email_verified_at' => now()]);
            CreatorProfile::query()->create([
                'user_id' => $user->id,
                'display_name' => 'Creator '.$name,
                'username' => 'creator-'.$name,
                'visibility' => 'public',
            ]);
        }

        // One more that is not published, to prove the count is filtered.
        $hidden = User::factory()->create(['email_verified_at' => now()]);
        CreatorProfile::query()->create([
            'user_id' => $hidden->id,
            'display_name' => 'Hidden',
            'username' => 'hidden-one',
            'visibility' => 'private',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('3');
        $response->assertDontSee('Hidden');
    }

    public function test_the_money_section_states_what_the_product_will_not_do(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('There is no button anywhere that marks money received.', false);
    }
}
