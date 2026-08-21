<?php

namespace App\Services\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Object storage for project media.
 *
 * MySQL only ever holds the disk name, the object key and metadata. The bytes
 * live on the configured S3-compatible disk (S3, R2, Spaces) in production and
 * on the private local disk in development.
 */
class MediaStorage
{
    /**
     * What may be uploaded, by real MIME type.
     *
     * A whitelist rather than a blocklist: the set of dangerous types is
     * open-ended and grows, while the set we actually need is short and known.
     *
     * The extension is checked too, and separately. A file can carry a
     * harmless MIME and a dangerous extension, and whichever one a downstream
     * consumer trusts is the one that matters.
     */
    public const ALLOWED = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif'],
        'video/mp4' => ['mp4', 'm4v'],
        'video/quicktime' => ['mov'],
        'video/webm' => ['webm'],
        'application/pdf' => ['pdf'],
    ];

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

    /**
     * Why this upload is refused, or null if it is fine.
     *
     * The MIME is read from the file's own contents, never from the
     * Content-Type header the browser sent — that header is supplied by
     * whoever is uploading and is exactly what an attacker controls.
     */
    public function refusalReason(UploadedFile $file): ?string
    {
        if (! $file->isValid()) {
            return __('That file did not upload correctly. Try again.');
        }

        $max = (int) config('vidlix.media.max_bytes', 512 * 1024 * 1024);

        if ($file->getSize() > $max) {
            return __('That file is larger than :size MB.', [
                'size' => (int) round($max / 1048576),
            ]);
        }

        // getMimeType() inspects the contents; getClientMimeType() would only
        // repeat what the uploader claimed.
        $mime = (string) $file->getMimeType();

        if (! isset(self::ALLOWED[$mime])) {
            return __('That kind of file cannot be uploaded here.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: '');

        if ($extension !== '' && ! in_array($extension, self::ALLOWED[$mime], true)) {
            // The contents and the name disagree. Refusing beats guessing which
            // of the two a later reader will believe.
            return __('The file name does not match what is inside it.');
        }

        return null;
    }

    public function assertAcceptable(UploadedFile $file): void
    {
        $reason = $this->refusalReason($file);

        if ($reason !== null) {
            throw ValidationException::withMessages(['file' => $reason]);
        }
    }

    public function put(string $key, UploadedFile $file): bool
    {
        // Checked here as well as at the controller, so no upload path can skip
        // it by forgetting to call the validator first.
        $this->assertAcceptable($file);

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
        // Resolving the disk is inside the try as well: a disk name that is no
        // longer configured throws here, and a download failing with a 500
        // teaches the caller nothing. Null means "cannot sign", which is
        // already the answer callers know how to handle.
        try {
            $filesystem = Storage::disk($disk);

            if (! method_exists($filesystem, 'temporaryUrl')) {
                return null;
            }

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
