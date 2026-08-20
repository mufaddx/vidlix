<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\CommissionRule;
use App\Models\ContactFormVersion;
use App\Models\Faq;
use App\Models\FeaturedCreator;
use App\Models\HomepageSection;
use App\Models\Role;
use App\Models\SocialPlatform;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use App\Services\Identity\AccountProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'creator' => 'Creator',
            'editor' => 'Editor',
            'brand' => 'Brand',
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

        // Starting taxonomy. Anyone may propose more; these are the approved list
        // brands filter against on day one.
        $categories = [
            'creator' => [
                'Fashion & Style', 'Beauty & Skincare', 'Food & Cooking', 'Travel',
                'Fitness & Health', 'Technology', 'Gaming', 'Comedy & Entertainment',
                'Education', 'Finance & Business', 'Lifestyle & Vlogs', 'Music & Dance',
                'Parenting & Family', 'Automotive', 'Art & Design',
            ],
            'editor' => [
                'Short-form / Reels', 'Long-form YouTube', 'Documentary', 'Wedding Films',
                'Corporate & Brand Films', 'Music Videos', 'Podcast Editing', 'Gaming Montage',
                'Motion Graphics', 'Colour Grading', 'Sound Design', 'Subtitles & Captions',
            ],
            'brand' => [
                'FMCG', 'Fashion & Apparel', 'Beauty & Personal Care', 'Technology & Electronics',
                'Food & Beverage', 'Travel & Hospitality', 'Health & Wellness', 'Finance & Fintech',
                'Education & EdTech', 'Automotive', 'Real Estate', 'Entertainment & Media',
            ],
        ];
        foreach ($categories as $type => $names) {
            foreach ($names as $index => $name) {
                Category::query()->updateOrCreate(
                    ['type' => $type, 'slug' => Str::slug($name)],
                    ['name' => $name, 'status' => 'approved', 'sort_order' => $index],
                );
            }
        }

        HomepageSection::query()->updateOrCreate(['key' => 'hero'], [
            'title' => 'The production desk for creators, brands and editors',
            'subtitle' => 'Discover talent, negotiate in the open, invoice properly, and settle through a real payment provider.',
            'is_visible' => true,
            'sort_order' => 1,
        ]);

        // Written out in full rather than left as placeholders. They describe
        // what this platform actually does; each still ends by saying it wants
        // counsel's eye before anyone relies on it commercially.
        $termsBody = <<<'TEXT'
Vidlix is a marketplace where creators, editors and brands find each other and work together. By using it you agree to these terms.

1. Accounts. You must give a real name and a working email address, and you are responsible for what happens under your login. Roles (creator, editor, brand) are applied for and granted after review.

2. What we do and do not do. Vidlix hosts the agreement, the conversation, the files and the money rail. Vidlix is not a party to the work itself and does not guarantee that either side performs.

3. Money. Payments are collected and payouts are made through licensed providers. A payment counts as made only when the provider confirms it to us over a signed webhook; a browser redirect is never treated as settlement. The ledger is append-only, and any balance shown is derived from it.

4. Instagram data. Follower and reach figures come from the official Meta APIs after you connect your account, and only for as long as that permission lasts. Vidlix does not scrape, estimate or invent numbers, and where no figure has been fetched none is displayed.

5. Content and files. You keep ownership of what you upload. You grant Vidlix the limited licence needed to store it, show it to the people you shared it with, and back it up.

6. Conduct. No spam, no scraping, no fake analytics, no impersonation, and no arranging payment off the platform to avoid fees on work that started here.

7. Suspension. An account can be suspended for a breach of these terms, for fraud, or where the law requires it. Money already owed is still settled.

8. Closing your account. You can close your account from Settings. Profiles, sessions and tokens are deleted. Financial records are retained with your identity removed, because the law requires them to be kept.

9. Liability. Nothing here excludes liability that cannot be excluded by law. Otherwise Vidlix's liability for any claim is limited to the fees it earned on the transaction the claim concerns.

10. Changes and law. Material changes are announced before they take effect. Indian law applies, and the courts of India have jurisdiction.

These terms were drafted to describe the platform accurately. Have them reviewed by counsel before you rely on them commercially.
TEXT;

        $privacyBody = <<<'TEXT'
This policy explains what Vidlix stores about you, why, and what you can do about it.

What we collect. Your name, email address and password hash. The profile you fill in for your role. Messages you send on the platform. Files you upload. Payment and payout records, including references returned by the payment provider. Session records (IP address and browser) so you can see and revoke your own logins. If you connect Instagram, the account name and the metrics the Meta APIs return.

What we do not collect. Vidlix does not buy data about you, does not scrape social accounts, and does not store card numbers or bank credentials, which stay with the payment provider.

Why we hold it. To run your account, to deliver messages, to settle money, and to meet legal record-keeping obligations.

Who else sees it. The people you are working with see what you send them. Our providers see what they need to do their job: the payment provider for money, the email provider for mail, the storage provider for files. We do not sell your data.

How long we keep it. Account and profile data until you close your account. Financial records for as long as the law requires, with your identity removed once the account is closed. Message and file history stays with the project it belongs to, since the other side has a legitimate record of the work.

Your rights. You can download everything we hold about you from Settings, and you can close your account from the same page. Corrections can be made by editing your profile, or by writing to the support desk.

Cookies. A session cookie keeps you logged in and a CSRF token protects forms. Both are essential. Analytics cookies, if any are ever added, are opt-in.

Security. Passwords are hashed, one-time codes are stored hashed with a short expiry, and every webhook is verified against the provider's signature before it is believed.

Contact. Write to the support desk from inside the platform, or to help@vidlix.in.

This policy was drafted to describe the platform accurately. Have it reviewed by counsel before you rely on it commercially.
TEXT;

        $refundBody = <<<'TEXT'
Refunds follow the payment provider's rules, the agreement between the two sides, and the outcome of any dispute.

Before work starts. If a project is cancelled before the editor or creator has begun, the money held for it is returned in full.

After work starts. What is returned depends on what was delivered. Raise it as a dispute from the project workspace and the evidence already on the platform, chat, files, invoices and payments, is used to decide it.

Timing. A refund is issued back to the original payment method through the provider. How long it takes to appear is the provider's and your bank's business, not ours, and we show a refund as complete only when the provider confirms it.

Subscriptions. A management subscription can be cancelled at any time and runs to the end of the period already paid for. Part-months are not refunded.

Platform fees. Where a payment is refunded in full, the platform fee on it is refunded too.

This policy was drafted to describe the platform accurately. Have it reviewed by counsel before you rely on it commercially.
TEXT;

        foreach ([
            ['how-it-works', 'How it works', 'Publish a verified profile. Brands inquire without an account. Negotiate, agree, and invoice. Provider webhooks confirm money. Deliver, approve, and settle.'],
            ['for-creators', 'For creators', 'Publish a public media kit, receive brand inquiries without forcing registration, and keep Instagram insights official.'],
            ['for-editors', 'For editors', 'Apply, get verified, then run projects with files, revision limits, and a real invoice path.'],
            ['for-brands', 'For brands', 'Verify the company, publish campaigns, compare applicants, and settle only after a signed provider webhook.'],
            ['press', 'Press', 'For product facts and brand assets, use the contact page. Do not invent metrics in coverage.'],
            ['security', 'Trust & security', 'RBAC, audit logs, webhook signatures, and a ledger that is never faked in the UI.'],
            ['about', 'About', 'Vidlix is a professional collaboration marketplace for creators, editors and brands.'],
            ['faq', 'FAQ', 'See the homepage FAQ. Additional policies are listed in the footer.'],
            ['contact', 'Contact', 'For platform support open a ticket after login, or use a creator public page to send a brand inquiry.'],
            ['careers', 'Careers', 'We hire operators, verification specialists, and finance ops. Write to the operator once mail is configured.'],
            ['terms', 'Terms of use', $termsBody],
            ['privacy', 'Privacy policy', $privacyBody],
            ['cookie', 'Cookie policy', 'Essential cookies keep sessions secure. Analytics cookies are opt-in.'],
            ['refund', 'Refund policy', $refundBody],
            ['dispute-policy', 'Dispute policy', 'Open a dispute from the project workspace. Evidence (chat, files, invoices, payments) is retained.'],
            ['creator-terms', 'Creator terms', 'Creators own their profile, their audience and their public page.'],
            ['brand-terms', 'Brand terms', 'Brands publish campaigns after verification and pay through the marketplace provider.'],
            ['editor-terms', 'Editor terms', 'Editors deliver through the project workspace. Raw files stay in object storage.'],
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
        Testimonial::query()->updateOrCreate(['author_name' => 'Mursalim'], [
            'author_role' => 'Creator',
            'quote' => 'The public page is the media kit. Brands write in without creating an account.',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        Testimonial::query()->updateOrCreate(['author_name' => 'Asha'], [
            'author_role' => 'Editor',
            'quote' => 'Revisions and files live on the project, not in a private chat dump.',
            'sort_order' => 2,
            'is_published' => true,
        ]);
        Testimonial::query()->updateOrCreate(['author_name' => 'ABC Brand'], [
            'author_role' => 'Brand',
            'quote' => 'Campaign applications come with a real profile, not a screenshot of insights.',
            'sort_order' => 3,
            'is_published' => true,
        ]);

        // The platform's own cut, in basis points. One active rule named
        // 'default' — the pricing page and the ledger both read this row, so
        // they cannot disagree.
        CommissionRule::query()->updateOrCreate(['slug' => 'default'], [
            'bps' => 1000,
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
        FeaturedCreator::query()->updateOrCreate(
            ['creator_profile_id' => $profile->id],
            ['priority' => 100, 'is_active' => true],
        );

        $provisioner = app(AccountProvisioner::class);

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

        Campaign::query()->updateOrCreate(
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

        CommissionRule::query()->updateOrCreate(['slug' => 'platform'], ['bps' => 1000, 'is_active' => true]);
        BlogPost::query()->updateOrCreate(['slug' => 'welcome'], [
            'title' => 'Welcome to the desk',
            'body' => 'Vidlix is live on real Laravel state. Payments stay pending until a provider webhook confirms them.',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
