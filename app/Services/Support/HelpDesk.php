<?php

namespace App\Services\Support;

use App\Models\Conversation;
use App\Models\ExternalContact;
use App\Models\Message;
use App\Models\SupportThread;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Email\OutboundEmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The help desk behind help@<domain>.
 *
 * Anyone can reach it — a signed-in member from the app, or a stranger by
 * email — and staff answer from the admin panel. Replies leave from help@ with
 * a real routing token, so the person can simply reply and stay in the same
 * thread; the help desk is a conversation, not a no-reply announcement.
 */
class HelpDesk
{
    public function __construct(
        private OutboundEmailService $outbound,
        private AuditLogger $audit,
    ) {}

    public function address(): ?string
    {
        $domain = (string) config('vidlix.email.inbound_domain');

        return $domain === '' ? null : config('vidlix.email.support_prefix', 'help').'@'.$domain;
    }

    /** Raised from inside the app by a signed-in member. */
    public function openFromMember(User $user, string $subject, string $body, string $priority = 'normal'): SupportThread
    {
        return DB::transaction(function () use ($user, $subject, $body, $priority) {
            $contact = ExternalContact::query()->firstOrCreate(
                ['email' => $user->email],
                ['name' => $user->name],
            );

            $conversation = $this->conversation($subject, $contact->id);
            $conversation->participants()->create(['user_id' => $user->id, 'role' => 'requester']);

            Message::query()->create([
                'conversation_id' => $conversation->id,
                'actor_user_id' => $user->id,
                'direction' => 'inbound',
                'body' => $body,
                'delivery_status' => 'stored',
            ]);

            $thread = SupportThread::query()->create([
                'conversation_id' => $conversation->id,
                'reference' => SupportThread::generateReference(),
                'user_id' => $user->id,
                'status' => 'open',
                'priority' => $priority,
            ]);
            $this->audit->record('support.opened', $thread, ['source' => 'app']);

            return $thread;
        });
    }

    /**
     * Mail that arrived at help@ with no routing token — a first contact.
     *
     * @param  array{from_email: ?string, from_name: ?string, subject: ?string, text: string, provider_event_id: ?string}  $mail
     */
    public function openFromEmail(array $mail): SupportThread
    {
        return DB::transaction(function () use ($mail) {
            $email = (string) ($mail['from_email'] ?? '');
            $contact = ExternalContact::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $mail['from_name'] ?? Str::before($email, '@')],
            );

            $conversation = $this->conversation(
                filled($mail['subject'] ?? null) ? (string) $mail['subject'] : 'Help request',
                $contact->id,
            );

            // A member who wrote from their registered address is linked, so
            // staff can see the account. Anyone else stays an external contact.
            $user = User::query()->where('email', $email)->first();
            if ($user) {
                $conversation->participants()->create(['user_id' => $user->id, 'role' => 'requester']);
            }

            Message::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'body' => (string) $mail['text'],
                'provider_message_id' => $mail['provider_event_id'] ?? null,
                'delivery_status' => 'received',
            ]);

            $thread = SupportThread::query()->create([
                'conversation_id' => $conversation->id,
                'reference' => SupportThread::generateReference(),
                'user_id' => $user?->id,
                'status' => 'open',
            ]);
            $this->audit->record('support.opened', $thread, ['source' => 'email']);

            return $thread;
        });
    }

    /** Staff answer. The reply is queued and only ever reported as the provider reports it. */
    public function reply(SupportThread $thread, User $staff, string $body): Message
    {
        $conversation = $thread->conversation;

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'actor_user_id' => $staff->id,
            'direction' => 'outbound',
            'body' => $body,
            'delivery_status' => $this->outbound->initialStatus(),
        ]);

        $conversation->update(['last_message_at' => now()]);
        $thread->update(['status' => 'pending', 'assigned_to_user_id' => $thread->assigned_to_user_id ?? $staff->id]);
        $this->outbound->queue($message);
        $this->audit->record('support.replied', $thread);

        return $message;
    }

    public function close(SupportThread $thread, User $staff): void
    {
        $thread->update(['status' => 'closed', 'closed_at' => now()]);
        $this->audit->record('support.closed', $thread, ['by' => $staff->id]);
    }

    private function conversation(string $subject, int $contactId): Conversation
    {
        return Conversation::query()->create([
            'conversation_uuid' => (string) Str::uuid(),
            'channel' => 'support',
            'subject' => Str::limit($subject, 160, ''),
            'status' => 'open',
            'owner_scope' => 'support',
            'external_contact_id' => $contactId,
            'routing_token' => Str::lower(Str::ulid()),
            'last_message_at' => now(),
        ]);
    }
}
