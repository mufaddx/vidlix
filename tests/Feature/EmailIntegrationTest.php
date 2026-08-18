<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ExternalContact;
use App\Models\Message;
use App\Services\Email\OutboundEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmailIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function configureSendGrid(): void
    {
        config([
            'vidlix.providers.email' => 'sendgrid',
            'vidlix.email.driver' => 'sendgrid',
            'vidlix.email.api_key' => 'SG.test-key',
            'vidlix.email.api_base' => 'https://api.sendgrid.com/v3',
            'vidlix.email.from_address' => 'hello@vidlix.test',
            'vidlix.email.inbound_domain' => 'inbound.vidlix.test',
            'vidlix.webhooks.email_secret' => 'mail-secret',
        ]);
    }

    private function conversation(): Conversation
    {
        $contact = ExternalContact::query()->create(['email' => 'brand@abc.test', 'name' => 'ABC']);

        return Conversation::query()->create([
            'conversation_uuid' => 'conv-uuid-1',
            'channel' => 'external_email',
            'subject' => 'Summer campaign',
            'status' => 'open',
            'external_contact_id' => $contact->id,
            'routing_token' => 'tok123abc',
            'last_message_at' => now(),
        ]);
    }

    private function signedPost(string $uri, array $payload, string $secret)
    {
        $body = json_encode($payload);

        return $this->call('POST', $uri, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $body, $secret),
        ], $body);
    }

    public function test_a_provider_accepting_a_message_is_not_recorded_as_delivered(): void
    {
        $this->configureSendGrid();
        Http::fake([
            'api.sendgrid.com/v3/mail/send' => Http::response('', 202, ['X-Message-Id' => 'sg-msg-1']),
        ]);

        $conversation = $this->conversation();
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'body' => 'Happy to help.',
            'delivery_status' => 'queued',
        ]);

        $result = app(OutboundEmailService::class)->send($message);

        $this->assertSame('accepted', $result['status']);
        $this->assertSame('accepted', $message->fresh()->delivery_status);
        $this->assertDatabaseHas('email_events', ['status' => 'accepted', 'provider' => 'sendgrid']);
    }

    public function test_the_reply_to_address_carries_the_routing_token(): void
    {
        $this->configureSendGrid();
        $conversation = $this->conversation();

        $this->assertSame(
            'reply+tok123abc@inbound.vidlix.test',
            app(OutboundEmailService::class)->replyAddressFor($conversation),
        );
    }

    public function test_only_a_delivery_event_may_mark_a_message_delivered(): void
    {
        $this->configureSendGrid();
        $conversation = $this->conversation();
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'body' => 'Happy to help.',
            'provider_message_id' => 'sg-msg-1',
            'delivery_status' => 'accepted',
        ]);

        $this->signedPost('/webhooks/email/events', [
            ['sg_event_id' => 'ev1', 'event' => 'delivered', 'sg_message_id' => 'sg-msg-1.filterdrecv'],
        ], 'mail-secret')->assertOk();

        $this->assertSame('delivered', $message->fresh()->delivery_status);
    }

    public function test_a_bounce_is_never_overwritten_by_a_later_delivered_event(): void
    {
        $this->configureSendGrid();
        $conversation = $this->conversation();
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'body' => 'Happy to help.',
            'provider_message_id' => 'sg-msg-2',
            'delivery_status' => 'accepted',
        ]);

        $this->signedPost('/webhooks/email/events', [
            ['sg_event_id' => 'ev2', 'event' => 'bounce', 'sg_message_id' => 'sg-msg-2', 'reason' => 'mailbox unavailable'],
        ], 'mail-secret')->assertOk();
        $this->signedPost('/webhooks/email/events', [
            ['sg_event_id' => 'ev3', 'event' => 'delivered', 'sg_message_id' => 'sg-msg-2'],
        ], 'mail-secret')->assertOk();

        $this->assertSame('bounced', $message->fresh()->delivery_status);
    }

    public function test_inbound_mail_is_routed_by_its_plus_address_token(): void
    {
        $this->configureSendGrid();
        $conversation = $this->conversation();

        $this->signedPost('/webhooks/email/inbound', [
            'id' => 'inb-1',
            'from' => 'ABC Brand <brand@abc.test>',
            'to' => 'reply+tok123abc@inbound.vidlix.test',
            'subject' => 'Re: Summer campaign',
            'text' => 'Sounds good, sending the brief.',
        ], 'mail-secret')->assertOk()->assertJsonPath('outcome', 'matched');

        $this->assertDatabaseHas('inbound_email_events', ['provider_event_id' => 'inb-1', 'match_status' => 'matched']);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'delivery_status' => 'received',
        ]);
    }

    public function test_inbound_mail_with_an_unknown_token_is_held_and_never_guessed_into_a_thread(): void
    {
        $this->configureSendGrid();
        // A conversation with this exact contact exists, so a "smart" match by
        // sender address would wrongly succeed. It must not.
        $this->conversation();

        $this->signedPost('/webhooks/email/inbound', [
            'id' => 'inb-2',
            'from' => 'brand@abc.test',
            'to' => 'reply+not-a-real-token@inbound.vidlix.test',
            'subject' => 'Private',
            'text' => 'Budget enclosed.',
        ], 'mail-secret')->assertOk()->assertJsonPath('outcome', 'unmatched');

        $this->assertDatabaseHas('inbound_email_events', ['provider_event_id' => 'inb-2', 'match_status' => 'unmatched']);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_postmark_style_inbound_json_is_understood(): void
    {
        $this->configureSendGrid();
        $conversation = $this->conversation();

        $this->signedPost('/webhooks/email/inbound', [
            'MessageID' => 'pm-1',
            'FromFull' => ['Email' => 'brand@abc.test', 'Name' => 'ABC'],
            'ToFull' => [['Email' => 'reply+tok123abc@inbound.vidlix.test', 'MailboxHash' => 'tok123abc']],
            'Subject' => 'Re: Summer campaign',
            'TextBody' => 'Confirmed.',
            'Headers' => [['Name' => 'In-Reply-To', 'Value' => '<abc@vidlix.test>']],
        ], 'mail-secret')->assertOk()->assertJsonPath('outcome', 'matched');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'in_reply_to' => '<abc@vidlix.test>',
        ]);
    }

    public function test_an_unsigned_inbound_post_is_rejected_and_stores_nothing(): void
    {
        $this->configureSendGrid();
        $this->conversation();

        $this->postJson('/webhooks/email/inbound', [
            'id' => 'inb-3',
            'to' => 'reply+tok123abc@inbound.vidlix.test',
            'text' => 'Forged.',
        ])->assertStatus(401);

        $this->assertDatabaseCount('inbound_email_events', 0);
        $this->assertDatabaseCount('messages', 0);
    }
}
