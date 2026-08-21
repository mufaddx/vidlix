<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Role;
use App\Models\User;
use App\Services\Creator\CreatorOnboardingService;
use App\Services\Deals\NegotiationService;
use App\Services\Forms\ContactFormBuilder;
use App\Services\Marketplace\MarketplaceEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Can a stranger reach somebody else's things by guessing an id?
 *
 * Every route below uses implicit model binding, which means the id comes
 * straight from the URL — so the only thing standing between a curious person
 * and another member's project, invoice, thread or file is the check that runs
 * after the model is loaded. This is the file that proves those checks exist.
 *
 * The expected answer is 404 rather than 403 throughout. A 403 confirms that
 * the thing exists, which is itself a disclosure: knowing that project 41 is
 * real, and that somebody is refusing you, is information you did not have.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $roleSlug = 'creator'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $role = Role::query()->firstOrCreate(['slug' => $roleSlug], ['name' => ucfirst($roleSlug)]);
        $user->roles()->attach($role);

        return $user->fresh();
    }

    /** A project belonging to two people who are not the stranger. */
    private function projectBetween(User $a, User $b): Project
    {
        return Project::query()->create([
            'name' => 'Private work',
            'status' => 'active',
            'owner_user_id' => $a->id,
            'counterparty_user_id' => $b->id,
            'total_amount_minor' => 500000,
        ]);
    }

    /* ------------------------------------------------------------- projects */

    public function test_a_stranger_cannot_open_someone_elses_project(): void
    {
        $project = $this->projectBetween($this->member('brand'), $this->member('creator'));

        $this->actingAs($this->member('editor'))
            ->get(route('app.projects.show', $project))
            ->assertNotFound();
    }

    public function test_a_stranger_cannot_move_someone_elses_project(): void
    {
        $project = $this->projectBetween($this->member('brand'), $this->member('creator'));
        $stranger = $this->member('editor');

        $this->actingAs($stranger)
            ->post(route('app.projects.transition', $project), ['status' => 'completed'])
            ->assertNotFound();

        // And it really did not move.
        $this->assertSame('active', $project->fresh()->status);
    }

    public function test_a_stranger_cannot_request_a_revision_or_a_payment(): void
    {
        $project = $this->projectBetween($this->member('brand'), $this->member('creator'));
        $stranger = $this->member('editor');

        $this->actingAs($stranger)
            ->post(route('app.projects.revision', $project), ['feedback' => 'Redo it'])
            ->assertNotFound();

        $this->actingAs($stranger)
            ->post(route('app.projects.pay', $project), ['amount_minor' => 100])
            ->assertNotFound();
    }

    public function test_both_sides_of_a_project_can_open_it(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');
        $project = $this->projectBetween($brand, $creator);

        // The refusals above are only meaningful if the people who should get
        // in actually do.
        $this->actingAs($brand)->get(route('app.projects.show', $project))->assertOk();
        $this->actingAs($creator)->get(route('app.projects.show', $project))->assertOk();
    }

    /* ---------------------------------------------------------------- files */

    public function test_a_stranger_cannot_download_a_project_file(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');
        $project = $this->projectBetween($brand, $creator);

        $file = ProjectFile::query()->create([
            'project_id' => $project->id,
            'uploaded_by' => $creator->id,
            'kind' => 'final',
            'disk' => 'local',
            'storage_key' => 'projects/'.$project->id.'/final.mp4',
            'original_name' => 'final.mp4',
        ]);

        // Refused before a signed URL is issued. A signed URL handed to the
        // wrong person keeps working for as long as it lives.
        $this->actingAs($this->member('editor'))
            ->get(route('app.project-files.download', $file))
            ->assertNotFound();
    }

    /* --------------------------------------------------------- conversations */

    public function test_a_stranger_cannot_read_a_thread(): void
    {
        $a = $this->member('creator');
        $b = $this->member('editor');
        $thread = app(MarketplaceEngine::class)->startInternalChat($a, $b, 'Private talk');

        $this->actingAs($this->member('brand'))
            ->get(route('inbox.show', $thread->conversation_uuid))
            ->assertNotFound();
    }

    public function test_a_stranger_cannot_reply_into_a_thread(): void
    {
        $a = $this->member('creator');
        $b = $this->member('editor');
        $thread = app(MarketplaceEngine::class)->startInternalChat($a, $b, 'Private talk');

        $this->actingAs($this->member('brand'))
            ->post(route('inbox.reply', $thread->conversation_uuid), ['body' => 'Injected'])
            ->assertNotFound();

        $this->assertDatabaseMissing('messages', ['body' => 'Injected']);
    }

    public function test_a_support_thread_is_not_reachable_from_a_members_inbox(): void
    {
        $user = $this->member();

        $support = Conversation::query()->create([
            'conversation_uuid' => (string) Str::uuid(),
            'channel' => 'support',
            'subject' => 'Help desk',
            'status' => 'open',
            'owner_user_id' => $user->id,
            'routing_token' => Str::lower(Str::ulid()),
        ]);

        // The help desk has its own screen with its own abilities. Owning the
        // thread is not enough to read it here.
        $this->actingAs($user)
            ->get(route('inbox.show', $support->conversation_uuid))
            ->assertNotFound();
    }

    /* -------------------------------------------------------------- invoices */

    public function test_a_stranger_cannot_open_an_invoice(): void
    {
        $seller = $this->member('creator');
        $buyer = $this->member('brand');

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-TEST-1',
            'seller_user_id' => $seller->id,
            'buyer_user_id' => $buyer->id,
            'status' => 'issued',
            'subtotal_minor' => 500000,
            'total_minor' => 500000,
            'currency' => 'INR',
        ]);

        $this->actingAs($this->member('editor'))
            ->get(route('app.invoices.pdf', $invoice))
            ->assertNotFound();
    }

    /* ---------------------------------------------------------- negotiations */

    public function test_a_stranger_cannot_read_or_accept_a_negotiation(): void
    {
        $brand = $this->member('brand');
        $creator = $this->member('creator');

        $negotiation = app(NegotiationService::class)->open($brand, $creator, [
            'amount_minor' => 500000,
        ]);

        $stranger = $this->member('editor');

        foreach ([
            ['get', route('app.negotiations.show', $negotiation->uuid)],
            ['post', route('app.negotiations.accept', $negotiation->uuid)],
            ['post', route('app.negotiations.counter', $negotiation->uuid)],
            ['post', route('app.negotiations.reject', $negotiation->uuid)],
            ['post', route('app.negotiations.cancel', $negotiation->uuid)],
        ] as [$method, $url]) {
            $this->actingAs($stranger)->{$method}($url, ['amount_minor' => 1])->assertNotFound();
        }

        $this->assertSame('offer_sent', $negotiation->fresh()->status);
    }

    /* ------------------------------------------------------- forms & domains */

    public function test_the_form_builder_only_ever_reaches_your_own_form(): void
    {
        $mine = $this->member('creator');
        app(CreatorOnboardingService::class)->provision($mine->id, 'Mine');

        $theirs = $this->member('creator');
        app(CreatorOnboardingService::class)->provision($theirs->id, 'Theirs');

        $builder = app(ContactFormBuilder::class);
        $theirForm = $builder->formFor($theirs->fresh(), 'creator');

        $this->actingAs($mine->fresh())
            ->post(route('app.contact-form.fields.add'), ['label' => 'Only mine', 'type' => 'text'])
            ->assertRedirect();

        // No route carries a form id, so there is nothing to point elsewhere —
        // and the other account is provably untouched.
        $keys = array_column($builder->workingSchema($theirForm)['fields'], 'key');
        $this->assertNotContains('only_mine', $keys);
    }

    /* -------------------------------------------------------- unauthenticated */

    public function test_signed_out_visitors_are_sent_to_sign_in_rather_than_shown_anything(): void
    {
        $project = $this->projectBetween($this->member('brand'), $this->member('creator'));

        $this->get(route('app.projects.show', $project))->assertRedirect(route('login'));
        $this->get(route('inbox'))->assertRedirect(route('login'));
        $this->get(route('app.negotiations'))->assertRedirect(route('login'));
    }

    public function test_a_member_session_is_not_a_way_into_the_admin_panel(): void
    {
        $this->actingAs($this->member())
            ->get('/admin')
            ->assertForbidden();
    }
}
