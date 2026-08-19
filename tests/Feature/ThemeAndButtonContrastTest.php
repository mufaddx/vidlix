<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the two things that actually broke the UI.
 *
 * A button variant used to set `background: transparent` at the same
 * specificity as `.btn:hover` but later in the file, so hovering kept the
 * transparent background while the text turned white — invisible on cream.
 * The fix was to make variants assign custom properties only, and these tests
 * keep it that way.
 */
class ThemeAndButtonContrastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function stylesheet(): string
    {
        return (string) file_get_contents(public_path('css/app.css'));
    }

    /** @return array<string, string> declarations inside one rule block */
    private function declarations(string $css, string $selector): array
    {
        $start = strpos($css, $selector.' {');
        $this->assertNotFalse($start, "Selector not found in stylesheet: {$selector}");

        $open = strpos($css, '{', $start);
        $close = strpos($css, '}', $open);
        $body = substr($css, $open + 1, $close - $open - 1);

        $declarations = [];
        foreach (explode(';', $body) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$property, $value] = explode(':', $line, 2);
            $property = trim($property);
            if ($property !== '' && ! str_starts_with($property, '/*')) {
                $declarations[$property] = trim($value);
            }
        }

        return $declarations;
    }

    public function test_button_variants_only_reassign_custom_properties(): void
    {
        $css = $this->stylesheet();

        foreach (['.btn.secondary', '.btn.ghost'] as $selector) {
            foreach (array_keys($this->declarations($css, $selector)) as $property) {
                $this->assertStringStartsWith(
                    '--',
                    $property,
                    "{$selector} sets `{$property}` directly. A variant that sets a real property can "
                    .'win over .btn:hover on specificity and leave the hover state half-applied, which '
                    .'is how the text became invisible. Set a --btn-* custom property instead.',
                );
            }
        }
    }

    public function test_the_hover_rule_always_changes_background_and_text_together(): void
    {
        $hover = $this->declarations($this->stylesheet(), '.btn:hover,
.btn:focus-visible');

        $this->assertSame('var(--btn-bg-hover)', $hover['background'] ?? null);
        $this->assertSame('var(--btn-ink-hover)', $hover['color'] ?? null);
        $this->assertSame('var(--btn-border-hover)', $hover['border-color'] ?? null);
    }

    public function test_both_dark_palettes_define_exactly_the_same_tokens(): void
    {
        $css = $this->stylesheet();

        // The dark palette is written twice: once for the OS preference and once
        // for the explicit toggle. A token added to only one of them would make
        // the two themes silently disagree.
        $viaMediaQuery = array_keys($this->declarations($css, ':root:not([data-theme="light"])'));
        $viaAttribute = array_keys($this->declarations($css, ':root[data-theme="dark"]'));

        sort($viaMediaQuery);
        sort($viaAttribute);

        $this->assertSame(
            $viaMediaQuery,
            $viaAttribute,
            'The prefers-color-scheme dark block and the [data-theme="dark"] block define different tokens.',
        );
    }

    public function test_every_dark_token_has_a_light_default(): void
    {
        $css = $this->stylesheet();
        $light = array_keys($this->declarations($css, ':root'));
        $dark = array_keys($this->declarations($css, ':root[data-theme="dark"]'));

        foreach ($dark as $token) {
            $this->assertContains(
                $token,
                $light,
                "`{$token}` is only defined in the dark palette, so light mode falls back to nothing.",
            );
        }
    }

    public function test_the_public_site_offers_a_theme_toggle(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-theme-toggle', false)
            ->assertSee('vidlix-theme', false);
    }

    public function test_the_login_screen_offers_a_theme_toggle(): void
    {
        $this->get('/login')->assertOk()->assertSee('data-theme-toggle', false);
    }

    public function test_the_workspace_offers_a_theme_toggle(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach(Role::query()->where('slug', 'creator')->first());
        app(CreatorOnboardingService::class)->provision($user->id, $user->name);

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-theme-toggle', false);
    }
}
