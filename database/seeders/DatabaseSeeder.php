<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use App\Models\ContactFormVersion;
use App\Models\Faq;
use App\Models\HomepageSection;
use App\Models\ManagementPlan;
use App\Models\Role;
use App\Models\SocialPlatform;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'creator' => 'Creator',
            'editor' => 'Editor',
            'brand' => 'Brand',
            'manager' => 'Manager',
            'super_admin' => 'Super Admin',
            'operations' => 'Operations',
            'verification' => 'Verification',
            'finance' => 'Finance',
            'support' => 'Support',
            'content' => 'Content',
        ];
        foreach ($roles as $slug => $name) {
            Role::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        $platforms = [
            ['name' => 'Instagram', 'slug' => 'instagram', 'username_url_template' => 'https://instagram.com/{username}', 'sort_order' => 1],
            ['name' => 'YouTube', 'slug' => 'youtube', 'username_url_template' => 'https://youtube.com/@{username}', 'sort_order' => 2],
            ['name' => 'X', 'slug' => 'x', 'username_url_template' => 'https://x.com/{username}', 'sort_order' => 3],
            ['name' => 'LinkedIn', 'slug' => 'linkedin', 'username_url_template' => null, 'supports_username' => false, 'sort_order' => 4],
        ];
        foreach ($platforms as $row) {
            SocialPlatform::query()->updateOrCreate(['slug' => $row['slug']], $row + [
                'supports_username' => $row['supports_username'] ?? true,
                'supports_full_url' => true,
                'is_active' => true,
            ]);
        }

        HomepageSection::query()->updateOrCreate(['key' => 'hero'], [
            'title' => 'The production desk for creators, brands and editors',
            'subtitle' => 'Discover talent, negotiate in the open, invoice properly, and settle through a real payment provider.',
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        foreach ([
            ['how-it-works', 'How it works', "Publish a verified profile. Brands inquire without an account. Negotiate, agree, and invoice. Provider webhooks confirm money. Deliver, approve, and settle."],
            ['for-creators', 'For creators', 'Publish a public media kit, receive brand inquiries without forcing registration, and keep Instagram insights official.'],
            ['for-editors', 'For editors', 'Apply, get verified, then run projects with files, revision limits, and a real invoice path.'],
            ['for-brands', 'For brands', 'Verify the company, publish campaigns, compare applicants, and settle only after a signed provider webhook.'],
            ['for-managers', 'For managers', 'Managers join by invitation. Access is delegated, revocable, and never a substitute for the creator account.'],
            ['press', 'Press', 'For product facts and brand assets, use the contact page. Do not invent metrics in coverage.'],
            ['security', 'Trust & security', 'RBAC, audit logs, webhook signatures, and a ledger that is never faked in the UI.'],
            ['about', 'About', 'Vidlix is a professional collaboration marketplace for creators, editors, brands, and authorized managers.'],
            ['faq', 'FAQ', 'See the homepage FAQ. Additional policies are listed in the footer.'],
            ['contact', 'Contact', 'For platform support open a ticket after login, or use a creator public page to send a brand inquiry.'],
            ['careers', 'Careers', 'We hire operators, verification specialists, and finance ops. Write to the operator once mail is configured.'],
            ['terms', 'Terms of use', 'Replace this CMS body with counsel-approved platform terms before production.'],
            ['privacy', 'Privacy policy', 'Replace this CMS body with a counsel-approved privacy policy before production.'],
            ['cookie', 'Cookie policy', 'Essential cookies keep sessions secure. Analytics cookies are opt-in.'],
            ['refund', 'Refund policy', 'Refunds follow the payment provider, the agreement, and dispute outcomes. Frontend redirects are never treated as settlement.'],
            ['dispute-policy', 'Dispute policy', 'Open a dispute from the project workspace. Evidence (chat, files, invoices, payments) is retained.'],
            ['creator-terms', 'Creator terms', 'Creators own their profile. Managers receive delegated permissions only.'],
            ['brand-terms', 'Brand terms', 'Brands publish campaigns after verification and pay through the marketplace provider.'],
            ['editor-terms', 'Editor terms', 'Editors deliver through the project workspace. Raw files stay in object storage.'],
            ['management-terms', 'Management terms', 'Management is a subscription. Access ends on revoke or expiry; history is preserved.'],
            ['community', 'Community guidelines', 'No spam, scraping, fake analytics, or off-platform payment circumvention.'],
        ] as [$slug, $title, $body]) {
            CmsPage::query()->updateOrCreate(['slug' => $slug], [
                'title' => $title,
                'body' => $body,
                'status' => 'published',
                'seo_title' => $title,
            ]);
        }

        Faq::query()->updateOrCreate(['question' => 'Do you fake Instagram numbers?'], [
            'answer' => 'No. Insights appear only after official Meta OAuth and permitted sync.',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        Faq::query()->updateOrCreate(['question' => 'Can a brand inquire without registering?'], [
            'answer' => 'Yes. Use the creator public URL contact form. The thread lands in the creator inbox.',
            'sort_order' => 2,
            'is_published' => true,
        ]);
        Faq::query()->updateOrCreate(['question' => 'When is a payment considered successful?'], [
            'answer' => 'Only after a signed provider webhook (or authoritative API state). A browser redirect is not enough.',
            'sort_order' => 3,
            'is_published' => true,
        ]);
        Faq::query()->updateOrCreate(['question' => 'Can a manager see payout accounts?'], [
            'answer' => 'Not by default. Bank and Instagram disconnect stay with the creator unless a permission is explicitly granted.',
            'sort_order' => 4,
            'is_published' => true,
        ]);

        \App\Models\Testimonial::query()->updateOrCreate(['author_name' => 'Mursalim'], [
            'author_role' => 'Creator',
            'quote' => 'The public page is the media kit. Brands write in without creating an account.',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        \App\Models\Testimonial::query()->updateOrCreate(['author_name' => 'Asha'], [
            'author_role' => 'Editor',
            'quote' => 'Revisions and files live on the project, not in a private chat dump.',
            'sort_order' => 2,
            'is_published' => true,
        ]);
        \App\Models\Testimonial::query()->updateOrCreate(['author_name' => 'ABC Brand'], [
            'author_role' => 'Brand',
            'quote' => 'Campaign applications come with a real profile, not a screenshot of insights.',
            'sort_order' => 3,
            'is_published' => true,
        ]);

        ManagementPlan::query()->updateOrCreate(['slug' => 'basic'], [
            'name' => 'Basic',
            'price_minor' => 499900,
            'currency' => 'INR',
            'features' => ['bullets' => ['Inbox assistance']],
            'is_active' => true,
        ]);
        ManagementPlan::query()->updateOrCreate(['slug' => 'pro'], [
            'name' => 'Pro',
            'price_minor' => 999900,
            'currency' => 'INR',
            'features' => ['bullets' => ['Inbox', 'Campaigns', 'Negotiation']],
            'is_active' => true,
        ]);
        ManagementPlan::query()->updateOrCreate(['slug' => 'premium'], [
            'name' => 'Premium',
            'price_minor' => 1999900,
            'currency' => 'INR',
            'features' => ['bullets' => ['Full collaboration management']],
            'is_active' => true,
        ]);

        $admin = User::query()->firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@vidlix.test')],
            [
                'name' => 'Vidlix Admin',
                'mobile' => env('ADMIN_MOBILE', '9999999999'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe_Admin1')),
                'email_verified_at' => now(),
                'status' => 'active',
            ],
        );
        $admin->roles()->syncWithoutDetaching(Role::query()->where('slug', 'super_admin')->pluck('id'));

        $demo = User::query()->firstOrCreate(
            ['email' => 'creator@vidlix.test'],
            [
                'name' => 'Mursalim Demo',
                'mobile' => '8888888888',
                'password' => Hash::make('Creator_Pass1'),
                'email_verified_at' => now(),
                'status' => 'active',
            ],
        );
        $demo->roles()->syncWithoutDetaching(Role::query()->where('slug', 'creator')->pluck('id'));
        if (! $demo->creatorProfile) {
            app(CreatorOnboardingService::class)->provision($demo->id, $demo->name);
        }
        $profile = $demo->creatorProfile()->first();
        $profile->update([
            'username' => 'mursalim',
            'bio' => 'Creator collaboration demo profile.',
            'visibility' => 'public',
            'profile_completion' => 80,
            'display_name' => 'Mursalim',
        ]);
        $page = $profile->publicPage;
        $payload = [
            'hero_title' => 'Mursalim',
            'hero_subtitle' => '@mursalim',
            'description' => 'Campaigns, long-form, and editor-ready production.',
            'cta_text' => 'Start a brief',
            'cta_visible' => true,
        ];
        $page->update([
            'draft_payload' => $payload,
            'published_payload' => $payload,
            'published_at' => now(),
            'status' => 'published',
        ]);
        ContactFormVersion::query()->where('contact_form_id', $page->contactForm->id)->update(['published_at' => now()]);
        \App\Models\FeaturedCreator::query()->updateOrCreate(
            ['creator_profile_id' => $profile->id],
            ['priority' => 100, 'is_active' => true],
        );

        $provisioner = app(\App\Services\Identity\AccountProvisioner::class);

        $editorUser = User::query()->firstOrCreate(
            ['email' => 'editor@vidlix.test'],
            [
                'name' => 'Asha Editor',
                'mobile' => '7777777777',
                'password' => Hash::make('Editor_Pass1'),
                'email_verified_at' => now(),
                'status' => 'active',
            ],
        );
        $editorUser->roles()->syncWithoutDetaching(Role::query()->where('slug', 'editor')->pluck('id'));
        $provisioner->provisionRole($editorUser, 'editor');
        $editorUser->refresh();
        $editorUser->editorProfile->update([
            'username' => 'asha',
            'application_status' => 'approved',
            'bio' => 'Reels and commercial editor.',
            'software' => ['Premiere Pro', 'After Effects'],
            'specializations' => ['Reels', 'YouTube'],
            'availability' => 'available',
        ]);

        $brandUser = User::query()->firstOrCreate(
            ['email' => 'brand@vidlix.test'],
            [
                'name' => 'ABC Brand',
                'mobile' => '6666666666',
                'password' => Hash::make('Brand_Pass1'),
                'email_verified_at' => now(),
                'status' => 'active',
            ],
        );
        $brandUser->roles()->syncWithoutDetaching(Role::query()->where('slug', 'brand')->pluck('id'));
        $provisioner->provisionRole($brandUser, 'brand');
        $brandUser->refresh();
        $brandUser->brandProfile->update([
            'company_name' => 'ABC Brand',
            'slug' => 'abc-brand',
            'verification_status' => 'verified',
            'industry' => 'FMCG',
            'website' => 'https://example.com',
        ]);

        \App\Models\Campaign::query()->updateOrCreate(
            ['slug' => 'summer-reels'],
            [
                'brand_profile_id' => $brandUser->brandProfile->id,
                'name' => 'Summer Reels',
                'status' => 'published',
                'objective' => 'Awareness',
                'brief' => 'Eight Instagram reels for summer drop.',
                'budget_minor' => 5000000,
                'platform' => 'Instagram',
            ],
        );

        \App\Models\CommissionRule::query()->updateOrCreate(['slug' => 'platform'], ['bps' => 1000, 'is_active' => true]);
        \App\Models\BlogPost::query()->updateOrCreate(['slug' => 'welcome'], [
            'title' => 'Welcome to the desk',
            'body' => 'Vidlix is live on real Laravel state. Payments stay pending until a provider webhook confirms them.',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
