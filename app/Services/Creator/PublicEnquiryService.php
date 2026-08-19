<?php

namespace App\Services\Creator;

use App\Contracts\EmailProviderInterface;
use App\Models\Conversation;
use App\Models\EmailEvent;
use App\Models\ExternalContact;
use App\Models\Message;
use App\Notifications\NewPublicInquiryNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Email\OutboundEmailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Enquiries from a public profile page that has no form builder — editors.
 *
 * Creators keep PublicInquiryService, because their page carries a versioned,
 * creator-designed form whose answers must be stored against the version that
 * produced them. An editor page has one fixed form, so it does not need any of
 * that machinery.
 */
class PublicEnquiryService
{
    public function __construct(
        private EmailProviderInterface $email,
        private OutboundEmailService $outbound,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  Model  $profile  an editor profile
     */
    public function submit(Model $profile, string $scope, array $input, ?string $ip): Conversation
    {
        $honeypot = config('vidlix.public_form_honeypot');
        if (filled($input[$honeypot] ?? null)) {
            // Silently refused: telling a bot why would only help it.
            throw ValidationException::withMessages(['form' => __('Unable to send this enquiry.')]);
        }

        $answers = $this->validated($input);

        return DB::transaction(function () use ($profile, $scope, $answers, $ip) {
            $contact = ExternalContact::query()->firstOrCreate(
                ['email' => $answers['email']],
                ['name' => $answers['name'], 'company' => $answers['company'] ?? null],
            );

            $conversation = Conversation::query()->create([
                'conversation_uuid' => (string) Str::uuid(),
                'channel' => 'external_email',
                'subject' => $answers['subject'],
                'status' => 'open',
                'owner_user_id' => $profile->user_id,
                'owner_scope' => $scope,
                'external_contact_id' => $contact->id,
                'routing_token' => Str::lower(Str::ulid()),
                'last_message_at' => now(),
            ]);

            $conversation->participants()->create([
                'user_id' => $profile->user_id,
                'role' => $scope,
            ]);

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'body' => $answers['message'],
                'delivery_status' => 'stored',
            ]);

            // Acknowledgement to the enquirer. Its status is whatever the
            // provider actually said — never a blanket "sent".
            $send = $this->email->sendThreadReply(
                $message,
                $contact->email,
                $this->outbound->identityFor($conversation->fresh()),
            );

            EmailEvent::query()->create([
                'message_id' => $message->id,
                'direction' => 'outbound_ack',
                'status' => $send['status'],
                'provider' => $this->email->name(),
                'provider_message_id' => $send['provider_message_id'],
                'detail' => $send['detail'],
            ]);

            $this->audit->record('public_enquiry.created', $conversation, [
                'scope' => $scope,
                'ip' => $ip,
            ]);

            $profile->user->notify(new NewPublicInquiryNotification($conversation));

            return $conversation;
        });
    }

    /** @return array<string, string> */
    private function validated(array $input): array
    {
        $answers = [];

        foreach (['name' => true, 'email' => true, 'subject' => true, 'message' => true, 'company' => false] as $field => $required) {
            $value = is_string($input[$field] ?? null) ? trim($input[$field]) : '';
            if ($required && $value === '') {
                throw ValidationException::withMessages([$field => __('This field is required.')]);
            }
            $answers[$field] = $value;
        }

        if (! filter_var($answers['email'], FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => __('Enter a valid email.')]);
        }

        return $answers;
    }
}
