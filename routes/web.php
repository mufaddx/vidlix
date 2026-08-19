<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminOpsController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\InboxController;
use App\Http\Controllers\App\InstagramController;
use App\Http\Controllers\App\ProjectFileController;
use App\Http\Controllers\App\PublicPageStudioController;
use App\Http\Controllers\App\WorkspaceController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Managers\ManagerInvitationController;
use App\Http\Controllers\Site\CreatorPublicController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Webhooks\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/creators', [HomeController::class, 'creators'])->name('creators.index');
Route::get('/editors', [HomeController::class, 'editors'])->name('editors.index');
Route::get('/editors/{username}', [HomeController::class, 'editorShow'])->name('editors.public');
Route::get('/brands', [HomeController::class, 'brands'])->name('brands.index');
Route::get('/brands/{slug}', [HomeController::class, 'brandShow'])->name('brands.public');
Route::get('/campaigns', [HomeController::class, 'campaigns'])->name('campaigns.index');
Route::get('/blog', [HomeController::class, 'blog'])->name('blog.index');
Route::get('/blog/{slug}', [HomeController::class, 'post'])->name('blog.show');
Route::get('/pricing', [HomeController::class, 'pricing'])->name('pricing');
Route::get('/p/{slug}', [HomeController::class, 'page'])->name('pages.show');
Route::get('/u/{username}', [CreatorPublicController::class, 'show'])->name('creators.public');
Route::post('/u/{username}/inquire', [CreatorPublicController::class, 'inquire'])->middleware('throttle:public-form')->name('creators.inquire');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
 | Manager invitations are reachable without an account on purpose: the invited
 | person usually does not have one yet and sets their password here. The
 | emailed token is the only credential, and it expires.
 */
Route::get('/manager/invite/{token}', [ManagerInvitationController::class, 'show'])->name('managers.invitation');
Route::post('/manager/invite/{token}', [ManagerInvitationController::class, 'activate'])
    ->middleware('throttle:register')
    ->name('managers.invitation.activate');

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
    Route::post('/workspace/manage', [WorkspaceController::class, 'manage'])->name('workspace.manage');
    Route::get('/integrations/instagram/callback', [InstagramController::class, 'callback'])->name('instagram.callback');

    Route::middleware('verified')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/creator/inbox', [InboxController::class, 'index'])->name('creator.inbox');
        Route::get('/creator/inbox/{uuid}', [InboxController::class, 'show'])->name('creator.inbox.show');
        Route::post('/creator/inbox/{uuid}/reply', [InboxController::class, 'reply'])->name('creator.inbox.reply');
        Route::get('/creator/public-page', [PublicPageStudioController::class, 'edit'])->name('creator.public-page');
        Route::post('/creator/public-page/draft', [PublicPageStudioController::class, 'saveDraft'])->name('creator.public-page.draft');
        Route::post('/creator/public-page/publish', [PublicPageStudioController::class, 'publish'])->name('creator.public-page.publish');
        Route::post('/creator/public-page/social', [PublicPageStudioController::class, 'addSocial'])->name('creator.public-page.social');
        Route::post('/creator/public-page/form', [PublicPageStudioController::class, 'saveForm'])->name('creator.public-page.form');

        Route::get('/editor', [WorkspaceController::class, 'editors'])->name('app.editors');
        Route::post('/editor/apply', [WorkspaceController::class, 'applyEditor'])->name('app.editors.apply');
        Route::get('/brand', [WorkspaceController::class, 'brandProfile'])->name('app.brand');
        Route::post('/brand', [WorkspaceController::class, 'saveBrand'])->name('app.brand.save');
        Route::get('/app/campaigns', [WorkspaceController::class, 'campaigns'])->name('app.campaigns');
        Route::post('/app/campaigns', [WorkspaceController::class, 'storeCampaign'])->name('app.campaigns.store');
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
        Route::post('/withdrawals', [WorkspaceController::class, 'withdraw'])->name('app.withdraw');
        Route::get('/management', [WorkspaceController::class, 'managers'])->name('app.managers');
        Route::post('/management/invite', [WorkspaceController::class, 'inviteManager'])->name('app.managers.invite');
        Route::post('/management/accept/{token}', [WorkspaceController::class, 'acceptInvite'])->name('app.managers.accept');
        Route::post('/management/subscribe', [WorkspaceController::class, 'subscribe'])->name('app.managers.subscribe');
        Route::post('/management/{assignment}/revoke', [WorkspaceController::class, 'revokeManager'])->name('app.managers.revoke');
        Route::get('/automations', [WorkspaceController::class, 'automations'])->name('app.automations');
        Route::post('/automations', [WorkspaceController::class, 'storeAutomation'])->name('app.automations.store');
        Route::get('/instagram', [WorkspaceController::class, 'instagram'])->name('app.instagram');
        Route::post('/instagram/connect', [InstagramController::class, 'connect'])->name('app.instagram.connect');
        Route::post('/instagram/sync', [InstagramController::class, 'sync'])->name('app.instagram.sync');
        Route::get('/project-files/{file}', [ProjectFileController::class, 'download'])->name('app.project-files.download');
        Route::get('/disputes', [WorkspaceController::class, 'disputes'])->name('app.disputes');
        Route::post('/disputes', [WorkspaceController::class, 'storeDispute'])->name('app.disputes.store');
        Route::get('/support', [WorkspaceController::class, 'tickets'])->name('app.tickets');
        Route::post('/support', [WorkspaceController::class, 'storeTicket'])->name('app.tickets.store');
        Route::get('/notifications', [WorkspaceController::class, 'notifications'])->name('app.notifications');
        Route::get('/settings', [WorkspaceController::class, 'settings'])->name('app.settings');
        Route::post('/settings/sessions/{id}', [WorkspaceController::class, 'revokeSession'])->name('app.sessions.revoke');
        Route::get('/portfolio', [WorkspaceController::class, 'portfolio'])->name('app.portfolio');
        Route::post('/portfolio', [WorkspaceController::class, 'storePortfolio'])->name('app.portfolio.store');
        Route::get('/proposals', [WorkspaceController::class, 'proposals'])->name('app.proposals');
        Route::post('/proposals', [WorkspaceController::class, 'storeProposal'])->name('app.proposals.store');
        Route::get('/invoices', [WorkspaceController::class, 'invoices'])->name('app.invoices');
    });

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('/cms', [AdminDashboardController::class, 'cms'])->name('cms');
        Route::post('/cms/sections/{section}', [AdminDashboardController::class, 'updateSection'])->name('cms.section');
        Route::get('/users', [AdminOpsController::class, 'users'])->name('users');
        Route::get('/verification', [AdminOpsController::class, 'verification'])->name('verification');
        Route::post('/editors/{editor}', [AdminOpsController::class, 'decideEditor'])->name('editors.decide');
        Route::post('/brands/{brand}', [AdminOpsController::class, 'decideBrand'])->name('brands.decide');
        Route::post('/campaigns/{campaign}', [AdminOpsController::class, 'decideCampaign'])->name('campaigns.decide');
        Route::get('/finance', [AdminOpsController::class, 'finance'])->name('finance');
        Route::post('/withdrawals/{withdrawal}', [AdminOpsController::class, 'withdrawal'])->name('withdrawals.update');
        Route::get('/disputes', [AdminOpsController::class, 'disputes'])->name('disputes');
        Route::post('/disputes/{dispute}', [AdminOpsController::class, 'resolveDispute'])->name('disputes.resolve');
        Route::get('/tickets', [AdminOpsController::class, 'tickets'])->name('tickets');
    });
});
