<?php

namespace App\Console\Commands;

use App\Services\Media\MediaStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * End-to-end check of the configured media disk.
 *
 * Writes a small object, reads it back, signs a URL, fetches that URL over the
 * network and deletes the object. Anything less would not prove the bucket is
 * actually usable from this host — credentials can be accepted for a write and
 * still fail on a signed read, which is the case that breaks downloads.
 */
class StorageCheckCommand extends Command
{
    protected $signature = 'vidlix:storage-check {--keep : Leave the probe object in place}';

    protected $description = 'Verify the configured object storage disk can write, sign, read and delete';

    public function handle(MediaStorage $media): int
    {
        $disk = $media->disk();
        $this->line('disk        : '.$disk);
        $this->line('driver      : '.config('filesystems.disks.'.$disk.'.driver'));

        if ($disk === 'local') {
            $this->warn('Media is on the local disk. Set FILESYSTEM_DISK=s3 (or MEDIA_DISK) for object storage.');
        } else {
            $this->line('bucket      : '.(config('filesystems.disks.'.$disk.'.bucket') ?: '(none)'));
            $this->line('endpoint    : '.(config('filesystems.disks.'.$disk.'.endpoint') ?: '(aws default)'));
            $this->line('region      : '.(config('filesystems.disks.'.$disk.'.region') ?: '(none)'));
        }

        $key = 'healthcheck/'.now()->format('Y/m').'/'.Str::uuid().'.txt';
        $body = 'vidlix storage check '.now()->toIso8601String();

        try {
            $this->line('');
            $this->line('1. write   ...');
            if (! Storage::disk($disk)->put($key, $body)) {
                $this->error('   write failed');

                return self::FAILURE;
            }
            $this->info('   ok  '.$key);

            $this->line('2. read    ...');
            $read = Storage::disk($disk)->get($key);
            if ($read !== $body) {
                $this->error('   content did not round-trip');

                return self::FAILURE;
            }
            $this->info('   ok  '.strlen($read).' bytes match');

            $this->line('3. sign    ...');
            $url = $media->temporaryUrl($disk, $key);
            if ($url === null) {
                $this->warn('   this disk cannot sign URLs; downloads will stream through the app');
            } else {
                $this->info('   ok  '.Str::limit($url, 70));

                $this->line('4. fetch   ...');
                if (config('filesystems.disks.'.$disk.'.driver') === 'local') {
                    // The local disk signs a route served by this app, so the
                    // fetch only succeeds against a running server. Skipping it
                    // keeps the check meaningful for the object-storage case.
                    $this->warn('   skipped: the local disk signs an app route, not a bucket URL');
                } else {
                    $fetched = @file_get_contents($url);
                    if ($fetched !== $body) {
                        $this->error('   the signed URL did not return the object');

                        return self::FAILURE;
                    }
                    $this->info('   ok  signed URL is fetchable straight from the bucket');
                }
            }
        } catch (Throwable $e) {
            $this->error('failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            if (! $this->option('keep')) {
                try {
                    Storage::disk($disk)->delete($key);
                    $this->line('5. cleanup ... ok');
                } catch (Throwable) {
                    $this->warn('5. cleanup ... could not delete '.$key);
                }
            }
        }

        $this->line('');
        $this->info('Object storage is working.');

        return self::SUCCESS;
    }
}
