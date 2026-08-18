<?php

namespace App\Services\Creator;

use App\Models\ContactForm;
use App\Models\ContactFormVersion;
use App\Models\CreatorProfile;
use App\Models\CreatorPublicPage;
use App\Models\InstagramAccount;
use App\Support\DefaultContactFormSchema;
use Illuminate\Support\Str;

class CreatorOnboardingService
{
    public function provision(int $userId, string $name): CreatorProfile
    {
        $username = $this->uniqueUsername($name);

        $profile = CreatorProfile::query()->create([
            'user_id' => $userId,
            'username' => $username,
            'display_name' => $name,
            'visibility' => 'private',
            'onboarding_step' => 'profile',
            'profile_completion' => 20,
            'instagram_connection_status' => 'disconnected',
        ]);

        $page = CreatorPublicPage::query()->create([
            'creator_profile_id' => $profile->id,
            'draft_payload' => [
                'hero_title' => $name,
                'hero_subtitle' => '@'.$username,
                'description' => '',
                'cta_text' => 'Work with me',
                'cta_visible' => true,
            ],
            'status' => 'draft',
            'theme' => 'professional',
        ]);

        $form = ContactForm::query()->create([
            'creator_public_page_id' => $page->id,
            'current_version' => 1,
        ]);

        ContactFormVersion::query()->create([
            'contact_form_id' => $form->id,
            'version_number' => 1,
            'schema_json' => DefaultContactFormSchema::make(),
            'published_at' => now(),
        ]);

        InstagramAccount::query()->create([
            'creator_profile_id' => $profile->id,
            'status' => 'disconnected',
        ]);

        return $profile;
    }

    private function uniqueUsername(string $name): string
    {
        $base = Str::slug($name) ?: 'creator';
        $candidate = $base;
        $i = 1;
        while (CreatorProfile::query()->where('username', $candidate)->exists()) {
            $candidate = $base.$i;
            $i++;
        }

        return $candidate;
    }
}
