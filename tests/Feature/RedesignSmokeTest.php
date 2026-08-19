<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The redesign changed the palette, the typeface and every component shape.
 * These check that the pages a visitor actually lands on still render, and
 * that all three surfaces load the one typeface rather than drifting apart
 * again.
 */
class RedesignSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public static function publicPages(): array
    {
        return [
            'home' => ['/'],
            'creators' => ['/creators'],
            'editors' => ['/editors'],
            'brands' => ['/brands'],
            'pricing' => ['/pricing'],
            'terms' => ['/p/terms'],
            'privacy' => ['/p/privacy'],
            'login' => ['/login'],
            'register' => ['/register'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_a_public_page_still_renders(string $path): void
    {
        $this->get($path)->assertOk();
    }

    public function test_every_surface_loads_the_same_typeface(): void
    {
        // The admin panel used to load no webfont at all, so it rendered in a
        // system face while the rest of the product used another - the same
        // product in two typefaces.
        foreach (['/', '/login'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('family=Inter', false)
                ->assertDontSee('DM+Sans', false)
                ->assertDontSee('Source+Serif', false);
        }
    }

    public function test_a_cms_page_without_a_description_does_not_leak_an_output_buffer(): void
    {
        // Blade reads @section('x', null) as "open a buffer and wait for
        // @endsection". The CMS page passed a nullable seo_description
        // straight in, so every legal page left a buffer open for the rest of
        // the request.
        $before = ob_get_level();

        $this->get('/p/terms')->assertOk();

        $this->assertSame(
            $before,
            ob_get_level(),
            'Rendering a CMS page left an output buffer open. Guard @section against null values.',
        );
    }

    public function test_a_page_without_its_own_description_keeps_the_site_default(): void
    {
        $this->get('/p/terms')
            ->assertOk()
            ->assertSee('Vidlix is a professional marketplace', false);
    }

    public function test_the_stylesheets_share_one_accent(): void
    {
        foreach (['app', 'admin', 'auth'] as $sheet) {
            $css = (string) file_get_contents(public_path("css/{$sheet}.css"));

            $this->assertStringContainsStringIgnoringCase(
                '#5b5ce2',
                $css,
                "css/{$sheet}.css does not use the product accent, so that surface has drifted.",
            );
        }
    }

    public function test_no_stylesheet_still_carries_the_old_palette(): void
    {
        foreach (['app', 'admin', 'auth'] as $sheet) {
            $css = (string) file_get_contents(public_path("css/{$sheet}.css"));

            foreach (['#7a3e28', '#f4f1ea', '59, 130, 246'] as $retired) {
                $this->assertStringNotContainsString(
                    $retired,
                    $css,
                    "css/{$sheet}.css still contains the retired value {$retired}.",
                );
            }
        }
    }
}
