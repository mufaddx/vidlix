<?php

namespace Tests\Feature;

use App\Support\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A stylesheet URL has to change when the stylesheet does.
 */
class AssetVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_the_stylesheet_url_carries_a_version(): void
    {
        // Without this, a returning visitor keeps the CSS their browser cached
        // the first time. After the interface was rebuilt, people who had been
        // before carried on seeing the old design and had no way to know.
        $this->assertMatchesRegularExpression('#/css/app\.css\?v=\d+#', Asset::url('css/app.css'));
    }

    public function test_the_version_follows_the_file(): void
    {
        $path = public_path('css/app.css');
        $original = filemtime($path);

        $before = Asset::url('css/app.css');

        touch($path, $original + 60);
        Cache::flush();
        $after = Asset::url('css/app.css');

        touch($path, $original);

        $this->assertNotSame($before, $after);
    }

    public function test_a_missing_file_does_not_break_the_page(): void
    {
        $this->assertStringContainsString('?v=0', Asset::url('css/not-here.css'));
    }

    public function test_public_pages_link_the_versioned_stylesheet(): void
    {
        $this->get('/')->assertOk()->assertSee('/css/app.css?v=', false);
        $this->get('/login')->assertOk()->assertSee('/css/auth.css?v=', false);
    }
}
