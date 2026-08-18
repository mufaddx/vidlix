<?php

namespace Tests\Unit;

use App\Models\SocialPlatform;
use App\Services\Social\SocialUrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialUrlResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_username_builds_platform_url_and_full_url_is_preserved(): void
    {
        $platform = SocialPlatform::query()->create([
            'name' => 'Instagram',
            'slug' => 'instagram-test',
            'username_url_template' => 'https://instagram.com/{username}',
            'supports_username' => true,
            'supports_full_url' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $resolver = new SocialUrlResolver;

        $this->assertSame(
            'https://instagram.com/mursalim',
            $resolver->resolve($platform, 'username', 'mursalim'),
        );
        $this->assertSame(
            'https://example.com/mursalim',
            $resolver->resolve($platform, 'full_url', 'https://example.com/mursalim'),
        );
    }
}
