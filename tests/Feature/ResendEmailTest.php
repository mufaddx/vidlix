<?php

namespace Tests\Feature;

use App\Contracts\EmailProviderInterface;
use App\Models\Conversation;
use App\Models\ExternalContact;
use App\Models\Message;
use App\Services\Email\OutboundEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResendEmailTest extends TestCase
{
    use RefreshDatabase;

    /** Base64 of a 24-byte key, in the whsec_ form Resend hands out. */
    private const SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';

    private function configureResend(): void
    {
        config([
            'vidlix.providers.email' => 'resend',
            'vidlix.email.driver' => 'resend',
            'vidlix.email.api_key' => 're_test_key',
            'vidlix.email.api_base' => 'https://api.resend.com',
            'vidlix.email.from_address' => 'noreply@vidlix.in',
            'vidlix.email.from_name' => 'Vidlix',
            'vidlix.email.inbound_domain' => 'inbound.vidlix.in',
            'vidlix.webhooks.email_secret' => self::SECRET,
            'vidlix.webhooks.schemes.email' => 'svix',
        ]);
    }

    private function conversation(): Conversation
    {
        $contact = ExternalContact::query()->create(['email' => 'brand@abc.test', 'name' => 'ABC']);

        return Conversation::query()->create([
            'conversation_uuid' => 'conv-resend-1',
            'channel' => 'external_email',
            'subject' => 'Summer campaign',
            'status' => 'open',
            'external_contact_id' => $contact->id,
            'routing_token' => 'tokresend',
            'last_message_at' => now(),
        ]);
    }

    /** Signs exactly the way Svix does: base64(hmac_sha256("{id}.{ts}.{body}")). */
    private function svixPost(array $payload, ?string $secret = null, ?int $timestamp = null, ?string $id = null)
    {
        $body = json_encode($payload);
        // Unique per call: Svix retries reuse the id, so a shared one would make
        // the second event in a test look like a replay.
        $id ??= 'msg_'.bin2hex(random_bytes(6));
        $timestamp ??= time();
        $key = base64_decode(substr($secret ?? self::SECRET, 6), true);
        $signature = base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$body, $key, true));

        return $this->call('POST', '/webhooks/email/events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_SVIX_ID' => $id,
            'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
            'HTTP_SVIX_SIGNATURE' => 'v1,'.$signature,
        ], $body);
    }

    public function test_the_resend_adapter_is_selected_and_reports_configured(): void
    {
        $this->configureResend();
        $provider = app(EmailProviderInterface::class);

        $this->assertSame('resend', $provider->name());
        $this->assertTrue($provider->isConfigured());
    }

    public function test_sending_reports_accepted_not_delivered(): void
    {
        $this->configureResend();
        Http::fake([
            'api.resend.com/emails' => Http::response(['id' => '49a3999c-0ce1-4ea6-ab68-afcd6dc2e794'], 200),
        ]);

        $message = Message::query()->create([
            'conversation_id' => $this->conversation()->id,
            'direction' => 'outbound',
            'body' => 'Happy to help.',
            'delivery_status' => 'queued',
        ]);

        $result = app(OutboundEmailService::class)->send($message);

        $this->assertSame('accepted', $result['status']);
        $this->assertSame('accepted', $message->fresh()->delivery_status);
        $this->assertDatabaseHas('email_events', ['status' => 'accepted', 'provider' => 'resend']);

        // The reply-to must carry the routing token or inbound replies cannot match.
        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['reply_to'] === ['reply+tokresend@inbound.vidlix.in']
                && $body['tags'][0]['name'] === 'vidlix_message_id';
        });
    }

    public function test_a_valid_svix_signature_marks_the_message_delivered(): void
    {
        $this->configureResend();
        $message = Message::query()->create([
            'conversation_id' => $this->conversation()->id,
            'direction' => 'outbound',
            'body' => 'Happy to help.',
            'provider_message_id' => 'resend-id-1',
            'delivery_status' => 'accepted',
        ]);

        $this->svixPost([
            'type' => 'email.delivered',
            'data' => ['email_id' => 'resend-id-1'],
        ])->assertOk();

        $this->assertSame('delivered', $message->fresh()->delivery_status);
    }

    public function test_a_bad_svix_signature_is_rejected_and_changes_nothing(): void
    {
        $this->configureResend();
        $message = Message::query()->create([
            'conversation_id' => $this->conversation()->id,
            'direction' => 'outbound',
            'body' => 'Happy to help.',
            'provider_message_id' => 'resend-id-2',
            'delivery_status' => 'accepted',
        ]);

        // Correctly formed, but signed with a different key.
        $this->svixPost(
            ['type' => 'email.delivered', 'data' => ['email_id' => 'resend-id-2']],
            'whsec_AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        )->assertStatus(401);

        $this->assertSame('accepted', $message->fresh()->delivery_status);
    }

    public function test_a_stale_timestamp_is_rejected_as_a_replay(): void
    {
        $this->configureResend();

        $this->svixPost(
            ['type' => 'email.delivered', 'data' => ['email_id' => 'resend-id-3']],
            null,
            time() - 3600,
        )->assertStatus(401);
    }

    public function test_a_non_delivery_event_is_ignored_and_never_becomes_inbound_mail(): void
    {
        $this->configureResend();

        // Resend lets you subscribe one endpoint to unrelated event families.
        // These must be ignored outright: routing them into the inbound path
        // would fill the operator triage queue with rows that are not mail.
        foreach (['contact.created', 'contact.deleted', 'domain.created'] as $i => $type) {
            $this->svixPost(['type' => $type, 'data' => ['id' => 'x'.$i]])
                ->assertOk()
                ->assertJsonPath('outcome', 'ignored');
        }

        $this->assertDatabaseCount('inbound_email_events', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_the_events_endpoint_can_never_create_a_conversation(): void
    {
        $this->configureResend();

        // A well-formed inbound payload sent to the events endpoint by mistake
        // must not be ingested as mail.
        $this->svixPost([
            'from' => 'brand@abc.test',
            'to' => 'reply+tokresend@inbound.vidlix.in',
            'subject' => 'Re: Summer campaign',
            'text' => 'Wrong endpoint.',
        ])->assertOk();

        $this->assertDatabaseCount('inbound_email_events', 0);
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_a_bounce_is_recorded_from_the_tag_when_the_id_is_unknown(): void
    {
        $this->configureResend();
        $message = Message::query()->create([
            'conversation_id' => $this->conversation()->id,
            'direction' => 'outbound',
            'body' => 'Happy to help.',
            'delivery_status' => 'accepted',
        ]);

        $this->svixPost([
            'type' => 'email.bounced',
            'data' => [
                'email_id' => 'an-id-we-never-stored',
                'tags' => [['name' => 'vidlix_message_id', 'value' => (string) $message->id]],
            ],
        ])->assertOk();

        $this->assertSame('bounced', $message->fresh()->delivery_status);
    }
}
