<?php

namespace App\Services\Email;

use App\Models\Conversation;
use App\Models\InboundEmailEvent;
use App\Models\Message;
use App\Services\Support\HelpDesk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stores inbound mail against the conversation its routing token names.
 *
 * If the token is absent or unknown the mail is recorded as unmatched and stops
 * there. It is never attached to a conversation by guessing from the sender
 * address or subject line, because that would leak one person's thread into
 * another person's inbox.
 */
class InboundEmailIngestor
{
    /**
     * @param  array{provider_event_id: ?string, from_email: ?string, from_name: ?string, subject: ?string, text: string, message_id: ?string, in_reply_to: ?string, recipients: list<string>, routing_token: ?string}  $mail
     * @return array{status: string, conversation_id: ?int, detail: string}
     */
    public function ingest(array $mail): array
    {
        $conversation = filled($mail['routing_token'] ?? null)
            ? Conversation::query()->where('routing_token', $mail['routing_token'])->first()
            : null;

        return DB::transaction(function () use ($mail, $conversation) {
            $event = InboundEmailEvent::query()->firstOrCreate(
                ['provider_event_id' => $mail['provider_event_id'] ?? 'inbound_'.Str::uuid()],
                [
                    'conversation_id' => $conversation?->id,
                    'match_status' => $conversation ? 'matched' : 'unmatched',
                    'from_email' => $mail['from_email'],
                    'subject' => $mail['subject'],
                    'raw_excerpt' => Str::limit((string) $mail['text'], 500),
                ],
            );

            if (! $event->wasRecentlyCreated) {
                return [
                    'status' => 'duplicate',
                    'conversation_id' => $event->conversation_id,
                    'detail' => 'This inbound message was already stored.',
                ];
            }

            if (! $conversation) {
                // Mail addressed to the help desk with no token is a first
                // contact, not a misrouted reply — open a ticket rather than
                // parking it in the unmatched queue.
                if ($this->addressedToHelpDesk($mail) && filled($mail['from_email'] ?? null)) {
                    $desk = app(HelpDesk::class);

                    // A reply to help@ often loses the plus-address token, so
                    // fall back to this sender's own open thread before opening
                    // a second one for the same conversation.
                    $existing = $desk->openThreadFor((string) $mail['from_email']);
                    if ($existing) {
                        $desk->appendInbound($existing, $mail);
                        $event->update([
                            'conversation_id' => $existing->conversation_id,
                            'match_status' => 'help_desk',
                        ]);

                        return [
                            'status' => 'help_desk_reply',
                            'conversation_id' => $existing->conversation_id,
                            'detail' => 'Added to help desk thread '.$existing->reference.'.',
                        ];
                    }

                    $thread = $desk->openFromEmail($mail);
                    $event->update([
                        'conversation_id' => $thread->conversation_id,
                        'match_status' => 'help_desk',
                    ]);

                    return [
                        'status' => 'help_desk',
                        'conversation_id' => $thread->conversation_id,
                        'detail' => 'Opened help desk thread '.$thread->reference.'.',
                    ];
                }

                return [
                    'status' => 'unmatched',
                    'conversation_id' => null,
                    'detail' => 'No routing token matched. The mail is held in inbound_email_events for an operator to route.',
                ];
            }

            Message::query()->create([
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'body' => (string) $mail['text'],
                'provider_message_id' => $mail['provider_event_id'],
                'email_message_id' => $mail['message_id'],
                'in_reply_to' => $mail['in_reply_to'],
                'delivery_status' => 'received',
            ]);
            $conversation->update(['last_message_at' => now(), 'status' => 'open']);

            return [
                'status' => 'matched',
                'conversation_id' => $conversation->id,
                'detail' => 'Inbound mail stored on its conversation.',
            ];
        });
    }

    /** True when one of the recipients is literally the help desk mailbox. */
    private function addressedToHelpDesk(array $mail): bool
    {
        $prefix = strtolower((string) config('vidlix.email.support_prefix', 'help'));
        $domain = strtolower((string) config('vidlix.email.inbound_domain'));
        if ($domain === '') {
            return false;
        }

        foreach ($mail['recipients'] ?? [] as $address) {
            if (strtolower($address) === $prefix.'@'.$domain) {
                return true;
            }
        }

        return false;
    }
}
