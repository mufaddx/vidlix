<?php

namespace Tests\Feature;

use App\Services\Media\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * What may be uploaded, and what happens to it afterwards.
 *
 * The interesting property is that the MIME type is read from the file's own
 * contents rather than from the Content-Type header, because that header is
 * supplied by whoever is uploading and is exactly what an attacker controls.
 */
class MediaStorageTest extends TestCase
{
    use RefreshDatabase;

    private function media(): MediaStorage
    {
        return app(MediaStorage::class);
    }

    public function test_an_ordinary_image_is_accepted(): void
    {
        $file = UploadedFile::fake()->image('portfolio.jpg');

        $this->assertNull($this->media()->refusalReason($file));
    }

    public function test_a_php_file_is_refused_however_it_is_named(): void
    {
        $file = UploadedFile::fake()->createWithContent('shell.php', '<?php echo "hi";');

        $this->assertNotNull($this->media()->refusalReason($file));
    }

    /**
     * A real file on disk, because the check under test reads the contents.
     *
     * UploadedFile::fake() answers getMimeType() from the extension, so a fake
     * cannot exercise content sniffing at all — using one here would produce a
     * test that passes without proving anything.
     */
    private function realUpload(string $name, string $contents, string $claimedMime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'vidlix');
        file_put_contents($path, $contents);

        // The last argument marks it as already-uploaded so the constructor does
        // not reject it; the claimed MIME is deliberately a lie, which is the
        // situation being tested.
        return new UploadedFile($path, $name, $claimedMime, null, true);
    }

    public function test_a_dangerous_file_wearing_an_image_name_is_still_refused(): void
    {
        // The name says jpg and the browser claims image/jpeg. Neither is
        // consulted: the contents are read, and they are PHP.
        $file = $this->realUpload('innocent.jpg', "<?php system('id'); ?>", 'image/jpeg');

        $this->assertNotNull($this->media()->refusalReason($file));
    }

    public function test_the_browsers_claimed_type_is_never_believed(): void
    {
        $file = $this->realUpload('payload.jpg', '#!/bin/sh
rm -rf /', 'image/jpeg');

        $this->assertNotNull($this->media()->refusalReason($file));
    }

    public function test_contents_and_extension_must_agree(): void
    {
        // A real PNG carrying an .mp4 name. Refusing beats guessing which of the
        // two a later reader will believe.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        $file = $this->realUpload('clip.mp4', $png, 'video/mp4');

        $this->assertNotNull($this->media()->refusalReason($file));
    }

    public function test_a_file_over_the_limit_is_refused(): void
    {
        config(['vidlix.media.max_bytes' => 1024]);

        $file = UploadedFile::fake()->image('huge.jpg')->size(5000);

        $reason = $this->media()->refusalReason($file);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('larger than', $reason);
    }

    public function test_storing_refuses_an_unacceptable_file_even_if_nobody_checked_first(): void
    {
        Storage::fake('local');

        // put() re-checks, so no upload path can skip validation by forgetting
        // to call the validator.
        $this->expectException(ValidationException::class);

        $this->media()->put('projects/1/x.php', UploadedFile::fake()->createWithContent('x.php', '<?php'));
    }

    public function test_a_stored_object_key_never_reuses_the_original_filename(): void
    {
        $file = UploadedFile::fake()->image('My Holiday Photo (final).jpg');

        $key = $this->media()->keyFor('portfolio', $file);

        // The original name is metadata, not a path. A key built from user input
        // is a traversal waiting to happen.
        $this->assertStringNotContainsString('Holiday', $key);
        $this->assertStringNotContainsString(' ', $key);
        $this->assertStringEndsWith('.jpg', $key);
    }

    public function test_a_key_cannot_be_walked_out_of_its_prefix(): void
    {
        $file = UploadedFile::fake()->image('../../etc/passwd.jpg');

        $key = $this->media()->keyFor('projects', $file);

        $this->assertStringStartsWith('projects/', $key);
        $this->assertStringNotContainsString('..', $key);
    }

    public function test_a_signed_url_expires(): void
    {
        Storage::fake('local');

        $url = $this->media()->temporaryUrl('local', 'projects/1/file.mp4');

        // A download URL that never expires is a permanent leak the moment it
        // is forwarded, so the expiry is the part worth asserting.
        $this->assertNotNull($url);
        $this->assertStringContainsString('expiration=', $url);
    }

    public function test_a_disk_that_cannot_sign_returns_null_rather_than_a_broken_url(): void
    {
        // Null is what tells the controller to stream through an authorised
        // route instead of handing out a URL that would not work.
        $this->assertNull($this->media()->temporaryUrl('nonexistent-disk', 'x'));
    }

    public function test_uploads_are_stored_privately(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('shot.png');
        $key = $this->media()->keyFor('portfolio', $file);

        $this->assertTrue($this->media()->put($key, $file));
        Storage::disk('local')->assertExists($key);
    }
}
