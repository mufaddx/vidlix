<?php

namespace App\Services\Forms;

use App\Contracts\EmailProviderInterface;
use App\Models\ContactFormSubmission;
use App\Models\Conversation;
use App\Models\CreatorProfile;
use App\Models\EditorProfile;
use App\Models\EmailEvent;
use App\Models\ExternalContact;
use App\Models\Message;
use App\Notifications\NewPublicInquiryNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Email\OutboundEmailService;
use App\Support\Forms\FormAnswers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A message sent from somebody's public page by a stranger.
 *
 * There used to be two of these — one for creators, whose page carried a
 * versioned form, and one for editors, who had a fixed four-field box. The
 * difference was never real: it existed because contact_forms hung off the
 * creator page. Now that a form belongs to a person, one service serves both.
 *
 * The owner is always passed in by the caller, resolved from the URL. Nothing
 * here reads an owner id out of the submitted data, because a form that carries
 * its own destination is a form anybody can point somewhere else.
 */
class PublicInquiries
{
    public function __construct(
        private EmailProviderInterface $email,
        private AuditLogger $audit,
        private OutboundEmailService $outbound,
        private ContactFormBuilder $forms,
    ) {}

    /**
     * @param  CreatorProfile|EditorProfile  $profile
     * @param  array<string, mixed>  $input
     */
    public function submit(Model $profile, string $scope, array $input, ?string $ip): Conversation
    {
        $owner = $profile->user;

        if ($owner === null) {
            throw ValidationException::withMessages([
                'form' => __('This profile is not accepting messages.'),
            ]);
        }

        $form = $this->forms->formFor($owner, $scope);
        $version = $this->forms->publishedVersion($form);

        if ($version === null) {
            throw ValidationException::withMessages([
                'form' => __('This profile is not accepting messages.'),
            ]);
        }

        /*
         | The honeypot is checked before validation and fails with the same
         | generic message a real error gives. Telling a bot precisely which
         | check it tripped is telling it how to pass next time.
         */
        $honeypot = config('vidlix.public_form_honeypot');

        if (filled($input[$honeypot] ?? null)) {
            throw ValidationException::withMessages([
                'form' => __('Unable to send your message.'),
            ]);
        }

        $answers = FormAnswers::validate($version->schema_json, $input);

        return DB::transaction(function () use ($profile, $owner, $scope, $form, $version, $answers, $ip) {
            $contact = ExternalContact::query()->firstOrCreate(
                ['email' => $answers['email']],
                ['name' => $answers['name'], 'company' => $answers['company'] ?? null],
            );

            $conversation = Conversation::query()->create([
                'conversation_uuid' => (string) Str::uuid(),
                'channel' => 'external_email',
                'subject' => $answers['subject'],
                'status' => 'open',
                // Kept for creators because existing queries read it; the owner
                // columns are what actually identify the inbox.
                'creator_profile_id' => $profile instanceof CreatorProfile ? $profile->id : null,
                'owner_user_id' => $owner->id,
                'owner_scope' => $scope,
                'external_contact_id' => $contact->id,
                'routing_token' => Str::lower(Str::ulid()),
                'last_message_at' => now(),
            ]);

            $conversation->participants()->create([
                'user_id' => $owner->id,
                'role' => $scope,
                'marketplace_role' => $scope,
            ]);

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'body' => $this->transcript($version->schema_json, $answers),
                'delivery_status' => 'stored',
            ]);

            ContactFormSubmission::query()->create([
                'contact_form_id' => $form->id,
                'contact_form_version_id' => $version->id,
                'conversation_id' => $conversation->id,
                'answers' => $answers,
                'ip_address' => $ip,
            ]);

            // The acknowledgement carries the Reply-To that inbound mail is
            // matched on, which is what puts the visitor's reply back on this
            // conversation rather than in a new one.
            $send = $this->email->sendThreadReply(
                $message,
                $contact->email,
                $this->outbound->identityFor($conversation),
            );

            EmailEvent::query()->create([
                'message_id' => $message->id,
                'direction' => 'outbound_ack',
                'status' => $send['status'],
                'provider' => $this->email->name(),
                'provider_message_id' => $send['provider_message_id'],
                'detail' => $send['detail'],
            ]);

            $message->update(['delivery_status' => $send['status']]);

            $this->audit->record('public_inquiry.created', $conversation, [
                'owner_scope' => $scope,
                'contact_form_version_id' => $version->id,
            ]);

            $owner->notify(new NewPublicInquiryNotification($conversation));

            return $conversation;
        });
    }

    /**
     * The message body as the owner should read it.
     *
     * A custom field is worthless if its answer never reaches the inbox, so
     * everything beyond the four standard fields is appended under its own
     * label rather than only living in the submission row.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, string>  $answers
     */
    private function transcript(array $schema, array $answers): string
    {
        $body = $answers['message'] ?? '';
        $extra = [];

        foreach ($schema['fields'] ?? [] as $field) {
            $key = $field['key'] ?? null;

            if (! is_string($key) || in_array($key, ['name', 'email', 'subject', 'message'], true)) {
                continue;
            }

            $value = $answers[$key] ?? '';

            if ($value === '') {
                continue;
            }

            $extra[] = ($field['label'] ?? $key).': '.$value;
        }

        if ($extra === []) {
            return $body;
        }

        return trim($body."\n\n---\n".implode("\n", $extra));
    }
}
