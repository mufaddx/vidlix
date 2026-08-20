<?php

namespace App\Models\Concerns;

use App\Services\Identity\UsernameRegistry;

/**
 * Keeps the username registry in step with a profile's own username column.
 *
 * This lives on the model rather than in the services that create profiles
 * because there is more than one such service, plus seeders, factories and
 * tests — and a handle that never reached the registry is a handle that
 * vidlix.in/{username} cannot resolve. Hooking the save is the only way to be
 * sure every path is covered.
 *
 * The registry stays the authority on whether a name may be taken; this only
 * makes sure it hears about it.
 */
trait RegistersUsername
{
    protected static function bootRegistersUsername(): void
    {
        static::saved(function ($profile): void {
            if (! filled($profile->username) || ! filled($profile->user_id)) {
                return;
            }

            // Only on the way in, or on a genuine rename. Every other save of a
            // profile — a bio edit, a follower-count sync — must leave the
            // registry alone, or a routine update would retire a live handle.
            if (! $profile->wasRecentlyCreated && ! $profile->wasChanged('username')) {
                return;
            }

            $user = $profile->user;

            if ($user === null) {
                return;
            }

            app(UsernameRegistry::class)->claim(
                $user,
                $profile->registryProfileType(),
                (int) $profile->getKey(),
                (string) $profile->username,
            );
        });
    }

    abstract public function registryProfileType(): string;
}
