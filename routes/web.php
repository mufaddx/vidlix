<?php

use App\Http\Controllers\Admin\AdminAudienceController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEmployeeController;
use App\Http\Controllers\Admin\AdminHelpDeskController;
use App\Http\Controllers\Admin\AdminMemberController;
use App\Http\Controllers\Admin\AdminOpsController;
use App\Http\Controllers\Admin\AdminPlatformController;
use App\Http\Controllers\App\ContactFormController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\DiscoveryController;
use App\Http\Controllers\App\InboxController;
use App\Http\Controllers\App\InstagramController;
use App\Http\Controllers\App\PrivacyController;
use App\Http\Controllers\App\ProjectFileController;
use App\Http\Controllers\App\PublicPageStudioController;
use App\Http\Controllers\App\RoleApplicationController;
use App\Http\Controllers\App\WorkspaceController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetFlowController;
use App\Http\Controllers\Auth\SignupFlowController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Site\AppDownloadController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\PublicProfileController;
use App\Http\Controllers\Webhooks\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/creators', [HomeController::class, 'creators'])->name('creators.index');
Route::get('/editors', [HomeController::class, 'editors'])->name('editors.index');
/*
 | The old role-prefixed profile addresses. A public profile now lives at
 | vidlix.in/{username} with no role in it, but these are printed in bios and
 | pasted into messages, so they redirect permanently rather than break.
 */
Route::permanentRedirect('/editors/{username}', '/{username}')->name('editors.public');
Route::permanentRedirect('/editor/{username}', '/{username}');
Route::post('/editors/{username}/enquire', fn (string $username) => redirect()->route('profile.contact', $username))
    ->name('editors.enquire');
Route::get('/brands', [HomeController::class, 'brands'])->name('brands.index');
Route::get('/download/android', [AppDownloadController::class, 'android'])->name('app.download.android');
Route::get('/brands/{slug}', [HomeController::class, 'brandShow'])->name('brands.public');
Route::get('/campaigns', [HomeController::class, 'campaigns'])->name('campaigns.index');
Route::get('/blog', [HomeController::class, 'blog'])->name('blog.index');
Route::get('/blog/{slug}', [HomeController::class, 'post'])->name('blog.show');
Route::get('/pricing', [HomeController::class, 'pricing'])->name('pricing');
Route::get('/p/{slug}', [HomeController::class, 'page'])->name('pages.show');
Route::permanentRedirect('/u/{username}', '/{username}')->name('creators.public');
Route::post('/u/{username}/inquire', fn (string $username) => redirect()->route('profile.contact', $username))
    ->name('creators.inquire');

Route::middleware('guest')->group(function () {
    /*
     | Sign-up runs in three steps and creates no user until all three are done.
     | The middle step is a real emailed code, so these endpoints are throttled
     | as tightly as the login form.
     */
    Route::get('/two-factor', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor', [TwoFactorController::class, 'verify'])->middleware('throttle:6,1')->name('two-factor.verify');
    Route::get('/register', [SignupFlowController::class, 'create'])->name('register')->middleware('feature:public_signup');
    Route::post('/register/start', [SignupFlowController::class, 'start'])->middleware('throttle:register')->name('register.start');
    Route::post('/register/verify', [SignupFlowController::class, 'verify'])->middleware('throttle:otp')->name('register.verify');
    Route::post('/register/resend', [SignupFlowController::class, 'resend'])->middleware('throttle:otp')->name('register.resend');
    Route::post('/register', [SignupFlowController::class, 'complete'])->middleware('throttle:register')->name('register.complete');

    Route::get('/forgot-password', [PasswordResetFlowController::class, 'create'])->name('password.request');
    Route::post('/forgot-password/start', [PasswordResetFlowController::class, 'start'])->middleware('throttle:otp')->name('password.start');
    Route::post('/forgot-password/verify', [PasswordResetFlowController::class, 'verify'])->middleware('throttle:otp')->name('password.verify');
    Route::post('/forgot-password/resend', [PasswordResetFlowController::class, 'resend'])->middleware('throttle:otp')->name('password.resend');
    Route::post('/forgot-password', [PasswordResetFlowController::class, 'complete'])->middleware('throttle:otp')->name('password.complete');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
 | The admin panel has its own front door. Signing in as a member is never a
 | way in, and a member who opens /admin sees a staff sign-in rather than their
 | own account.
 */
Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'store'])->middleware('throttle:login')->name('admin.login.store');
Route::post('/admin/logout', [AdminAuthController::class, 'destroy'])->middleware('auth')->name('admin.logout');

Route::prefix('webhooks')->middleware('throttle:webhooks')->group(function () {
    Route::match(['get', 'post'], 'meta', [WebhookController::class, 'meta']);
    Route::post('email/inbound', [WebhookController::class, 'emailInbound']);
    Route::post('email/events', [WebhookController::class, 'emailEvents']);
    Route::post('payment', [WebhookController::class, 'payment']);
    Route::post('payout', [WebhookController::class, 'payout']);
});

Route::middleware('auth')->group(function () {
    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
    Route::post('/workspace/switch', [WorkspaceController::class, 'switch'])->name('workspace.switch');
    Route::get('/integrations/instagram/callback', [InstagramController::class, 'callback'])->name('instagram.callback');

    Route::middleware('verified')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        // One inbox for every role, so the URL is no longer creator-shaped.
        // The old paths still resolve because they have been linked and bookmarked.
        Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
        Route::get('/inbox/{uuid}', [InboxController::class, 'show'])->name('inbox.show');
        Route::post('/inbox/{uuid}/reply', [InboxController::class, 'reply'])->name('inbox.reply');
        Route::post('/inbox/{uuid}/archive', [InboxController::class, 'archive'])->name('inbox.archive');
        Route::post('/inbox/{uuid}/mute', [InboxController::class, 'mute'])->name('inbox.mute');
        Route::post('/inbox/{uuid}/report', [InboxController::class, 'report'])->name('inbox.report');
        Route::post('/inbox/{uuid}/block', [InboxController::class, 'block'])->name('inbox.block');
        Route::redirect('/creator/inbox', '/inbox');
        Route::get('/creator/inbox/{uuid}', fn (string $uuid) => redirect('/inbox/'.$uuid));
        Route::get('/creator/public-page', [PublicPageStudioController::class, 'edit'])->name('creator.public-page');
        Route::post('/creator/public-page/draft', [PublicPageStudioController::class, 'saveDraft'])->name('creator.public-page.draft');
        Route::post('/creator/public-page/publish', [PublicPageStudioController::class, 'publish'])->name('creator.public-page.publish');
        Route::post('/creator/public-page/social', [PublicPageStudioController::class, 'addSocial'])->name('creator.public-page.social');
        // The form lives in its own builder now, shared with editors.
        Route::redirect('/creator/public-page/form', '/contact-form')->name('creator.public-page.form');

        /*
         | The contact form builder. No route takes a form id: which form you
         | are editing comes from your own account and your active role, so
         | there is nothing in the request to point at somebody else's form.
         */
        Route::get('/contact-form', [ContactFormController::class, 'edit'])->name('app.contact-form');
        Route::post('/contact-form', [ContactFormController::class, 'save'])->name('app.contact-form.save');
        Route::post('/contact-form/fields', [ContactFormController::class, 'addField'])->name('app.contact-form.fields.add');
        Route::delete('/contact-form/fields/{key}', [ContactFormController::class, 'removeField'])->name('app.contact-form.fields.remove');
        Route::post('/contact-form/reorder', [ContactFormController::class, 'reorder'])->name('app.contact-form.reorder');
        Route::post('/contact-form/toggle', [ContactFormController::class, 'toggle'])->name('app.contact-form.toggle');

        Route::get('/roles', [RoleApplicationController::class, 'index'])->name('app.roles');
        Route::post('/roles/apply', [RoleApplicationController::class, 'apply'])->name('app.roles.apply');
        Route::post('/roles/creator-categories', [RoleApplicationController::class, 'saveCreatorCategories'])->name('app.roles.creator-categories');

        Route::get('/editor', [WorkspaceController::class, 'editors'])->name('app.editors');
        Route::post('/editor/apply', [WorkspaceController::class, 'applyEditor'])->name('app.editors.apply');
        Route::get('/discover', [DiscoveryController::class, 'index'])->name('app.discover');
        Route::post('/discover/{creator}/connect', [DiscoveryController::class, 'connect'])->name('app.discover.connect');

        Route::get('/brand', [WorkspaceController::class, 'brandProfile'])->name('app.brand');
        Route::post('/brand', [WorkspaceController::class, 'saveBrand'])->name('app.brand.save');
        Route::post('/brand/documents', [WorkspaceController::class, 'uploadBrandDocument'])->name('app.brand.documents.store');
        Route::delete('/brand/documents/{document}', [WorkspaceController::class, 'deleteBrandDocument'])->name('app.brand.documents.destroy');
        Route::get('/app/campaigns', [WorkspaceController::class, 'campaigns'])->name('app.campaigns');
        Route::post('/app/campaigns', [WorkspaceController::class, 'storeCampaign'])->name('app.campaigns.store')->middleware('feature:campaign_publishing');
        Route::post('/app/campaigns/{campaign}/submit', [WorkspaceController::class, 'submitCampaign'])->name('app.campaigns.submit');
        Route::post('/app/campaigns/{campaign}/apply', [WorkspaceController::class, 'applyCampaign'])->name('app.campaigns.apply');
        Route::get('/applications', [WorkspaceController::class, 'applications'])->name('app.applications');
        Route::post('/applications/{application}', [WorkspaceController::class, 'applicationStatus'])->name('app.applications.status');
        Route::get('/projects', [WorkspaceController::class, 'projects'])->name('app.projects');
        Route::post('/projects', [WorkspaceController::class, 'storeProject'])->name('app.projects.store');
        Route::get('/projects/{project}', [WorkspaceController::class, 'showProject'])->name('app.projects.show');
        Route::post('/projects/{project}/transition', [WorkspaceController::class, 'projectTransition'])->name('app.projects.transition');
        Route::post('/projects/{project}/file', [WorkspaceController::class, 'projectFile'])->name('app.projects.file');
        Route::post('/projects/{project}/revision', [WorkspaceController::class, 'projectRevision'])->name('app.projects.revision');
        Route::post('/projects/{project}/pay', [WorkspaceController::class, 'projectPay'])->name('app.projects.pay');
        Route::get('/chat', [WorkspaceController::class, 'chat'])->name('app.chat');
        Route::post('/chat', [WorkspaceController::class, 'startChat'])->name('app.chat.start');
        Route::get('/chat/{uuid}', [WorkspaceController::class, 'showChat'])->name('app.chat.show');
        Route::post('/chat/{uuid}', [WorkspaceController::class, 'chatReply'])->name('app.chat.reply');
        Route::get('/earnings', [WorkspaceController::class, 'earnings'])->name('app.earnings');
        Route::post('/withdrawals', [WorkspaceController::class, 'withdraw'])->name('app.withdraw')->middleware('feature:withdrawals');
        Route::get('/automations', [WorkspaceController::class, 'automations'])->name('app.automations');
        Route::post('/automations', [WorkspaceController::class, 'storeAutomation'])->name('app.automations.store');
        Route::get('/instagram', [WorkspaceController::class, 'instagram'])->name('app.instagram');
        Route::post('/instagram/connect', [InstagramController::class, 'connect'])->name('app.instagram.connect')->middleware('feature:instagram_sync');
        Route::post('/instagram/sync', [InstagramController::class, 'sync'])->name('app.instagram.sync');
        Route::get('/project-files/{file}', [ProjectFileController::class, 'download'])->name('app.project-files.download');
        Route::get('/disputes', [WorkspaceController::class, 'disputes'])->name('app.disputes')->middleware('can:disputes.resolve');
        Route::post('/disputes', [WorkspaceController::class, 'storeDispute'])->name('app.disputes.store');
        Route::get('/support', [WorkspaceController::class, 'tickets'])->name('app.tickets');
        Route::post('/support', [WorkspaceController::class, 'storeTicket'])->name('app.tickets.store');
        Route::get('/notifications', [WorkspaceController::class, 'notifications'])->name('app.notifications');
        Route::post('/notifications/preferences', [WorkspaceController::class, 'saveNotificationPreferences'])->name('app.notifications.preferences');
        Route::post('/notifications/read', [WorkspaceController::class, 'markNotificationsRead'])->name('app.notifications.read');
        Route::get('/settings', [WorkspaceController::class, 'settings'])->name('app.settings');
        Route::post('/settings/sessions/{id}', [WorkspaceController::class, 'revokeSession'])->name('app.sessions.revoke');

        // Your own data, and the door out. Deletion is throttled and asks for
        // the password again, because it cannot be undone.
        Route::get('/settings/two-factor', [TwoFactorController::class, 'settings'])->name('app.two-factor');
        Route::post('/settings/two-factor/begin', [TwoFactorController::class, 'begin'])->name('app.two-factor.begin');
        Route::post('/settings/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('app.two-factor.confirm');
        Route::post('/settings/two-factor/recovery', [TwoFactorController::class, 'regenerate'])->name('app.two-factor.recovery');
        Route::delete('/settings/two-factor', [TwoFactorController::class, 'disable'])->name('app.two-factor.disable');
        Route::get('/settings/privacy', [PrivacyController::class, 'show'])->name('app.privacy');
        Route::get('/settings/privacy/export', [PrivacyController::class, 'export'])->middleware('throttle:6,1')->name('app.privacy.export');
        Route::delete('/settings/privacy', [PrivacyController::class, 'destroy'])->middleware('throttle:3,60')->name('app.privacy.destroy');
        Route::get('/portfolio', [WorkspaceController::class, 'portfolio'])->name('app.portfolio');
        Route::post('/portfolio', [WorkspaceController::class, 'storePortfolio'])->name('app.portfolio.store');
        Route::get('/proposals', [WorkspaceController::class, 'proposals'])->name('app.proposals');
        Route::post('/proposals', [WorkspaceController::class, 'storeProposal'])->name('app.proposals.store');
        Route::get('/invoices', [WorkspaceController::class, 'invoices'])->name('app.invoices');
        Route::get('/invoices/{invoice}/pdf', [WorkspaceController::class, 'invoicePdf'])->name('app.invoices.pdf');
    });

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('/cms', [AdminDashboardController::class, 'cms'])->name('cms')->middleware('can:cms.manage');
        Route::post('/cms/sections/{section}', [AdminDashboardController::class, 'updateSection'])->name('cms.section')->middleware('can:cms.manage');
        // Superseded by the member profile; kept so existing links still work.
        Route::get('/users', fn () => redirect()->route('admin.members'))->name('users')->middleware('can:users.view');
        Route::get('/verification', [AdminOpsController::class, 'verification'])->name('verification')->middleware('can:verification.decide');
        Route::post('/editors/{editor}', [AdminOpsController::class, 'decideEditor'])->name('editors.decide')->middleware('can:verification.decide');
        Route::post('/brands/{brand}', [AdminOpsController::class, 'decideBrand'])->name('brands.decide')->middleware('can:verification.decide');
        Route::post('/campaigns/{campaign}', [AdminOpsController::class, 'decideCampaign'])->name('campaigns.decide')->middleware('can:verification.decide');
        Route::get('/finance', [AdminOpsController::class, 'finance'])->name('finance')->middleware('can:finance.view');
        Route::post('/withdrawals/{withdrawal}', [AdminOpsController::class, 'withdrawal'])->name('withdrawals.update')->middleware('can:finance.approve_payouts');
        Route::get('/disputes', [AdminOpsController::class, 'disputes'])->name('disputes')->middleware('can:disputes.resolve');
        Route::post('/disputes/{dispute}', [AdminOpsController::class, 'resolveDispute'])->name('disputes.resolve')->middleware('can:disputes.resolve');
        Route::get('/tickets', [AdminOpsController::class, 'tickets'])->name('tickets')->middleware('can:support.view');
        Route::get('/reports', [AdminOpsController::class, 'reports'])->name('reports')->middleware('can:support.view');
        Route::post('/reports/{report}', [AdminOpsController::class, 'resolveReport'])->name('reports.resolve')->middleware('can:support.reply');

        // One member, everything about them, on one page.
        Route::get('/members', [AdminMemberController::class, 'index'])->name('members')->middleware('can:users.view');
        Route::get('/members/{user}', [AdminMemberController::class, 'show'])->name('members.show')->middleware('can:users.view');
        Route::post('/members/{user}/status', [AdminMemberController::class, 'updateStatus'])->name('members.status')->middleware('can:users.manage');
        Route::post('/members/{user}/visibility', [AdminMemberController::class, 'updateVisibility'])->name('members.visibility')->middleware('can:users.manage');

        // Influencers
        Route::get('/influencers', [AdminAudienceController::class, 'influencers'])->name('influencers')->middleware('can:users.view');
        Route::get('/influencers/categories', [AdminAudienceController::class, 'categories'])->defaults('type', 'creator')->name('influencers.categories')->middleware('can:categories.approve');

        // Brands
        Route::get('/brands-list', [AdminAudienceController::class, 'brands'])->name('brands')->middleware('can:users.view');
        Route::get('/brands-list/verification', [AdminOpsController::class, 'verification'])->name('brands.verification')->middleware('can:verification.decide');
        Route::get('/brands-list/campaigns', [AdminAudienceController::class, 'brandCampaigns'])->name('brands.campaigns')->middleware('can:verification.decide');

        // Editors
        Route::get('/editors-list', [AdminAudienceController::class, 'editors'])->name('editors')->middleware('can:users.view');
        Route::get('/editors-list/verification', [AdminOpsController::class, 'verification'])->name('editors.verification')->middleware('can:verification.decide');
        Route::get('/editors-list/categories', [AdminAudienceController::class, 'categories'])->defaults('type', 'editor')->name('editors.categories')->middleware('can:categories.approve');

        Route::post('/categories/{category}', [AdminAudienceController::class, 'decideCategory'])->name('categories.decide')->middleware('can:categories.approve');

        // Help desk — help@<domain> and in-app tickets land here.
        Route::get('/help-desk', [AdminHelpDeskController::class, 'index'])->name('help-desk')->middleware('can:support.view');
        Route::get('/help-desk/{thread}', [AdminHelpDeskController::class, 'show'])->name('help-desk.show')->middleware('can:support.view');
        Route::post('/help-desk/{thread}/reply', [AdminHelpDeskController::class, 'reply'])->name('help-desk.reply')->middleware('can:support.reply');
        Route::post('/help-desk/{thread}/close', [AdminHelpDeskController::class, 'close'])->name('help-desk.close')->middleware('can:support.reply');

        // Staff accounts. Granting abilities is itself gated.
        Route::get('/health', [AdminPlatformController::class, 'health'])->name('health')->middleware('can:platform.manage');
        Route::get('/platform', [AdminPlatformController::class, 'index'])->name('platform')->middleware('can:platform.manage');
        Route::post('/platform/flag', [AdminPlatformController::class, 'saveFlag'])->name('platform.flag')->middleware('can:platform.manage');
        Route::post('/platform/maintenance', [AdminPlatformController::class, 'saveMaintenance'])->name('platform.maintenance')->middleware('can:platform.manage');
        Route::get('/employees', [AdminEmployeeController::class, 'index'])->name('employees')->middleware('can:employees.manage');
        Route::post('/employees', [AdminEmployeeController::class, 'store'])->name('employees.store')->middleware('can:employees.manage');
        Route::post('/employees/{employee}/abilities', [AdminEmployeeController::class, 'updateAbilities'])->name('employees.abilities')->middleware('can:employees.manage');
        Route::post('/employees/{employee}/status', [AdminEmployeeController::class, 'updateStatus'])->name('employees.status')->middleware('can:employees.manage');
    });
});

/*
 | vidlix.in/{username} — the public profile, and the last route in the file.
 |
 | Its position is the point: the router tries every real path first, so a
 | handle can never shadow a page the application owns. The reserved list in the
 | registry covers the paths that do not exist yet, and the pattern keeps the
 | catch-all from swallowing files like favicon.ico.
 */
Route::get('/{username}', [PublicProfileController::class, 'show'])
    ->where('username', '[A-Za-z0-9._-]+')
    ->name('profile.show');

Route::get('/{username}/contact', [PublicProfileController::class, 'contact'])
    ->where('username', '[A-Za-z0-9._-]+')
    ->name('profile.contact');

Route::post('/{username}/contact', [PublicProfileController::class, 'submit'])
    ->where('username', '[A-Za-z0-9._-]+')
    ->middleware(['throttle:public-form', 'feature:public_enquiries', 'turnstile'])
    ->name('profile.contact.submit');
