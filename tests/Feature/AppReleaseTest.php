<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The app asks what it should be running, because nothing else will tell it.
 */
class AppReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_older_build_is_told_an_update_exists(): void
    {
        config(['vidlix.app.android_version' => '1.2.0', 'vidlix.app.android_minimum' => '1.0.0']);

        $this->getJson('/api/v1/app/android?version=1.0.2')
            ->assertOk()
            ->assertJsonPath('data.update_available', true)
            // Newer is not the same as necessary: this build still works.
            ->assertJsonPath('data.update_required', false);
    }

    public function test_the_current_build_is_left_alone(): void
    {
        config(['vidlix.app.android_version' => '1.2.0', 'vidlix.app.android_minimum' => '1.0.0']);

        $this->getJson('/api/v1/app/android?version=1.2.0')
            ->assertOk()
            ->assertJsonPath('data.update_available', false);
    }

    public function test_a_build_below_the_minimum_is_told_it_must_update(): void
    {
        config(['vidlix.app.android_version' => '1.2.0', 'vidlix.app.android_minimum' => '1.1.0']);

        $this->getJson('/api/v1/app/android?version=1.0.0')
            ->assertOk()
            ->assertJsonPath('data.update_required', true);
    }

    public function test_version_comparison_is_numeric_not_alphabetical(): void
    {
        config(['vidlix.app.android_version' => '1.10.0', 'vidlix.app.android_minimum' => '1.0.0']);

        // "1.9.0" sorts after "1.10.0" as text, which would tell somebody on an
        // older build that they were up to date.
        $this->getJson('/api/v1/app/android?version=1.9.0')
            ->assertJsonPath('data.update_available', true);
    }

    public function test_the_download_is_a_404_until_a_build_is_published(): void
    {
        Storage::fake('public');

        $this->get('/download/android')->assertNotFound();
    }

    public function test_a_published_build_downloads_with_the_right_type(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('releases/vidlix-android.apk', 'not-really-an-apk');

        $this->get('/download/android')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.android.package-archive');
    }

    public function test_the_size_reported_is_the_size_actually_served(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('releases/vidlix-android.apk', str_repeat('x', 1234));

        $this->getJson('/api/v1/app/android?version=0.0.1')
            ->assertJsonPath('data.size_bytes', 1234);
    }
}
