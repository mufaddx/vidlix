<?php

namespace App\Services\Identity;

use App\Models\BrandProfile;
use App\Models\EditorProfile;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use Illuminate\Support\Str;

class AccountProvisioner
{
    public function __construct(
        private CreatorOnboardingService $creators,
        private UsernameRegistry $usernames,
    ) {}

    public function provisionRole(User $user, string $role): void
    {
        match ($role) {
            'creator' => $this->creator($user),
            'editor' => $this->editor($user),
            'brand' => $this->brand($user),
            default => null,
        };

        LedgerAccount::query()->firstOrCreate([
            'user_id' => $user->id,
            'kind' => 'earnings',
            'currency' => config('vidlix.currency', 'INR'),
        ]);
    }

    private function creator(User $user): void
    {
        if (! $user->creatorProfile) {
            $this->creators->provision($user->id, $user->name);
        }
    }

    private function editor(User $user): void
    {
        if ($user->editorProfile) {
            return;
        }

        // Suggested by the registry so it cannot collide with a creator handle.
        $username = $this->usernames->suggestFrom($user->name, 'editor');

        $profile = EditorProfile::query()->create([
            'user_id' => $user->id,
            'username' => $username,
            'display_name' => $user->name,
            'application_status' => 'not_applied',
        ]);

        $this->usernames->claim($user, 'editor', $profile->id, $username);
    }

    private function brand(User $user): void
    {
        if ($user->brandProfile) {
            return;
        }
        BrandProfile::query()->create([
            'user_id' => $user->id,
            'company_name' => $user->name,
            'slug' => $this->unique('brand', $user->name),
            'business_email' => $user->email,
            'verification_status' => 'unverified',
        ]);
    }

    private function unique(string $kind, string $name): string
    {
        $base = Str::slug($name) ?: $kind;
        $candidate = $base;
        $i = 1;
        $exists = fn (string $c) => match ($kind) {
            'editor' => EditorProfile::query()->where('username', $c)->exists(),
            'brand' => BrandProfile::query()->where('slug', $c)->exists(),
            default => false,
        };
        while ($exists($candidate)) {
            $candidate = $base.$i;
            $i++;
        }

        return $candidate;
    }
}
