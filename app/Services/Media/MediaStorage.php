<?php

namespace App\Services\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Object storage for project media.
 *
 * MySQL only ever holds the disk name, the object key and metadata. The bytes
 * live on the configured S3-compatible disk (S3, R2, Spaces) in production and
 * on the private local disk in development.
 */
class MediaStorage
{
    public function disk(): string
    {
        return (string) config('vidlix.media.disk', 'local');
    }

    public function filesystem(): Filesystem
    {
        return Storage::disk($this->disk());
    }

    /** Object keys are opaque; the original filename is metadata, not a path. */
    public function keyFor(string $prefix, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');

        return trim($prefix, '/').'/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;
    }

    public function put(string $key, UploadedFile $file): bool
    {
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            return false;
        }

        try {
            return (bool) $this->filesystem()->put($key, $stream, ['visibility' => 'private']);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Short-lived download URL. S3-compatible disks sign it; the local disk has
     * no signing, so callers fall back to a controller-authorised stream.
     */
    public function temporaryUrl(string $disk, string $key): ?string
    {
        $filesystem = Storage::disk($disk);
        if (! method_exists($filesystem, 'temporaryUrl')) {
            return null;
        }

        try {
            return $filesystem->temporaryUrl(
                $key,
                now()->addMinutes((int) config('vidlix.media.signed_url_minutes', 15)),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public function delete(string $disk, string $key): void
    {
        Storage::disk($disk)->delete($key);
    }
}
