<?php

namespace App\Services\Creator;

use App\Contracts\EmailProviderInterface;
use App\Models\ContactFormSubmission;
use App\Models\Conversation;
use App\Models\CreatorProfile;
use App\Models\EmailEvent;
use App\Models\ExternalContact;
use App\Models\Message;
use App\Notifications\NewPublicInquiryNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Email\OutboundEmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicInquiryService
{
    public function __construct(
        private EmailProviderInterface $email,
        private AuditLogger $audit,
        private OutboundEmailService $outbound,
    ) {}

    public function submit(CreatorProfile $profile, array $input, ?string $ip): Conversation
    {
        $page = $profile->publicPage;
        if (! $profile->isPublished() || $page === null) {
            throw ValidationException::withMessages(['form' => __('This profile is not accepting inquiries.')]);
        }

        $form = $page->contactForm;
        $version = $form?->publishedVersion();
        if ($form === null || $version === null) {
            throw ValidationException::withMessages(['form' => __('The contact form is not published.')]);
        }

        $honeypot = config('vidlix.public_form_honeypot');
        if (filled($input[$honeypot] ?? null)) {
            throw ValidationException::withMessages(['form' => __('Unable to send inquiry.')]);
        }

        $answers = $this->validateAgainstSchema($version->schema_json, $input);

        return DB::transaction(function () use ($profile, $form, $version, $answers, $ip) {
            $contact = ExternalContact::query()->firstOrCreate(
                ['email' => $answers['email']],
                ['name' => $answers['name'], 'company' => $answers['company'] ?? null],
            );

            $conversation = Conversation::query()->create([
                'conversation_uuid' => (string) Str::uuid(),
                'channel' => 'external_email',
                'subject' => $answers['subject'],
                'status' => 'open',
                'creator_profile_id' => $profile->id,
                'external_contact_id' => $contact->id,
                'routing_token' => Str::lower(Str::ulid()),
                'last_message_at' => now(),
            ]);

            $conversation->participants()->create([
                'user_id' => $profile->user_id,
                'role' => 'creator',
            ]);

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'body' => $answers['message'],
                'delivery_status' => 'stored',
            ]);

            ContactFormSubmission::query()->create([
                'contact_form_id' => $form->id,
                'contact_form_version_id' => $version->id,
                'conversation_id' => $conversation->id,
                'answers' => $answers,
                'ip_address' => $ip,
            ]);

            // Replies must land back on this conversation, so the Reply-To is the
            // routing address inbound mail is matched on.
            $send = $this->email->sendThreadReply(
                $message,
                $contact->email,
                $this->outbound->replyAddressFor($conversation) ?? (string) config('vidlix.email.from_address'),
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
                'creator_profile_id' => $profile->id,
            ]);

            $profile->user->notify(new NewPublicInquiryNotification($conversation));

            return $conversation;
        });
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validateAgainstSchema(array $schema, array $input): array
    {
        $answers = [];
        foreach ($schema['fields'] ?? [] as $field) {
            $key = $field['key'];
            $value = is_string($input[$key] ?? null) ? trim($input[$key]) : '';
            if (($field['required'] ?? false) && $value === '') {
                throw ValidationException::withMessages([$key => __('This field is required.')]);
            }
            if ($key === 'email' && $value !== '' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['email' => __('Enter a valid email.')]);
            }
            $answers[$key] = $value;
        }

        return $answers;
    }
}
