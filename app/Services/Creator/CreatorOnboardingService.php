<?php

namespace App\Services\Creator;

use App\Models\ContactForm;
use App\Models\ContactFormVersion;
use App\Models\CreatorProfile;
use App\Models\CreatorPublicPage;
use App\Models\InstagramAccount;
use App\Services\Identity\UsernameRegistry;
use App\Support\DefaultContactFormSchema;

class CreatorOnboardingService
{
    public function __construct(private UsernameRegistry $registry) {}

    public function provision(int $userId, string $name): CreatorProfile
    {
        // The registry decides the handle, not this service. It is the only
        // thing that knows what editors have already taken and which words the
        // router owns.
        $username = $this->registry->suggestFrom($name, 'creator');

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

        $this->registry->claim($profile->user, 'creator', $profile->id, $username);

        return $profile;
    }
}
