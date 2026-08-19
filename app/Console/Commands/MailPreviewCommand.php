<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\CreatorProfile;
use App\Models\EditorProfile;
use App\Models\EmailEvent;
use App\Models\ExternalContact;
use App\Models\Message;
use App\Models\User;
use App\Services\Email\OutboundEmailService;
use App\Services\Email\OutboundIdentity;
use App\Services\Email\SystemMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sends one real example of each sender identity to a chosen address.
 *
 * Reading the code tells you which From a thread should use; only a delivered
 * message tells you what the recipient actually sees in their client. The
 * scaffolding rows are removed afterwards so a preview never leaves stray
 * conversations behind.
 */
class MailPreviewCommand extends Command
{
    protected $signature = 'vidlix:mail-preview {email : Where to send the examples}';

    protected $description = 'Send one example of each outbound identity (creator, editor, system)';

    public function handle(OutboundEmailService $outbound, SystemMailer $system): int
    {
        $to = $this->argument('email');
        $this->line('Sending three examples to '.$to);
        $this->newLine();

        $created = [];

        try {
            $created = $this->scaffold($to);

            $this->report('1. Creator thread', $outbound->identityFor($created['creatorThread']));
            $result = $outbound->send($created['creatorMessage']);
            $this->outcome($result);

            $this->report('2. Editor thread', $outbound->identityFor($created['editorThread']));
            $result = $outbound->send($created['editorMessage']);
            $this->outcome($result);

            $this->report('3. System (noreply)', $system->identity());
            $result = $system->send($to, 'Your Vidlix sign-in code', $this->systemBody());
            $this->outcome($result);
        } finally {
            $this->cleanup($created);
        }

        $this->newLine();
        $this->info('Done. Check the inbox — the three should look like three different senders.');

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function scaffold(string $to): array
    {
        $contact = ExternalContact::query()->firstOrCreate(
            ['email' => $to],
            ['name' => 'Mail preview', 'company' => 'Preview'],
        );

        $creatorProfile = CreatorProfile::query()->first();
        $creatorThread = Conversation::query()->create([
            'conversation_uuid' => (string) Str::uuid(),
            'channel' => 'external_email',
            'subject' => 'Re: Collaboration for your summer campaign',
            'status' => 'open',
            'creator_profile_id' => $creatorProfile?->id,
            'owner_user_id' => $creatorProfile?->user_id,
            'owner_scope' => 'creator',
            'external_contact_id' => $contact->id,
            'routing_token' => 'preview'.Str::lower(Str::random(8)),
            'last_message_at' => now(),
        ]);
        $creatorMessage = Message::query()->create([
            'conversation_id' => $creatorThread->id,
            'direction' => 'outbound',
            'body' => $this->creatorBody($creatorProfile?->display_name ?? 'the creator'),
            'delivery_status' => 'queued',
        ]);

        $editorProfile = EditorProfile::query()->first();
        $editorUser = $editorProfile?->user ?? User::query()->first();
        $editorThread = Conversation::query()->create([
            'conversation_uuid' => (string) Str::uuid(),
            'channel' => 'external_email',
            'subject' => 'Re: Documentary edit — 20 minutes, 4K',
            'status' => 'open',
            'owner_user_id' => $editorUser?->id,
            'owner_scope' => 'editor',
            'external_contact_id' => $contact->id,
            'routing_token' => 'preview'.Str::lower(Str::random(8)),
            'last_message_at' => now(),
        ]);
        $editorMessage = Message::query()->create([
            'conversation_id' => $editorThread->id,
            'direction' => 'outbound',
            'body' => $this->editorBody($editorProfile?->display_name ?? 'the editor'),
            'delivery_status' => 'queued',
        ]);

        return compact('contact', 'creatorThread', 'creatorMessage', 'editorThread', 'editorMessage');
    }

    private function cleanup(array $created): void
    {
        if ($created === []) {
            return;
        }

        DB::transaction(function () use ($created) {
            foreach (['creatorThread', 'editorThread'] as $key) {
                $conversation = $created[$key] ?? null;
                if (! $conversation) {
                    continue;
                }
                EmailEvent::query()->whereIn('message_id', $conversation->messages()->pluck('id'))->delete();
                $conversation->messages()->delete();
                $conversation->participants()->delete();
                $conversation->delete();
            }
            $created['contact']?->delete();
        });

        $this->line('Preview scaffolding removed.');
    }

    private function report(string $label, OutboundIdentity $identity): void
    {
        $this->newLine();
        $this->line($label);
        $this->line('   From     : '.$identity->fromName.' <'.$identity->fromAddress.'>');
        $this->line('   Reply-To : '.$identity->replyTo);
    }

    private function outcome(array $result): void
    {
        $result['status'] === 'accepted'
            ? $this->info('   Result   : '.$result['status'].' — '.$result['detail'])
            : $this->warn('   Result   : '.$result['status'].' — '.$result['detail']);
    }

    private function creatorBody(string $name): string
    {
        return <<<TEXT
        Hi,

        Thanks for reaching out about the summer campaign — the brief looks like a good fit for what I make.

        Here is what I would suggest:

          • 3 Reels (30–45s each), shot and edited by me
          • 1 carousel post with the product in use
          • 2 story frames with a swipe-up on launch day

        I can deliver the first cut within 6 working days of receiving the product, with one round of revisions included. Usage rights for 90 days on your handles are included; paid amplification can be added.

        Happy to jump on a call if it is easier. Just reply to this email — it comes straight back to me.

        Best,
        {$name}
        TEXT;
    }

    private function editorBody(string $name): string
    {
        return <<<TEXT
        Hi,

        Thanks for the enquiry about the 20-minute documentary edit.

        Based on what you described, here is how I would approach it:

          • Assembly from your rushes, with a paper-edit first so we agree the story before fine cutting
          • Sound pass: dialogue clean-up, music bed, and a light mix
          • Colour grade for a consistent look across the interview and B-roll
          • Subtitles burned in or as a sidecar .srt, whichever you prefer

        Timeline: 8–10 working days for the first cut once I have the footage and a transcript, then two rounds of revisions.

        If you can share a short sample of the rushes I can confirm the quote precisely. Reply to this email and it comes back to me directly.

        Best,
        {$name}
        TEXT;
    }

    private function systemBody(): string
    {
        return <<<'TEXT'
        Your Vidlix sign-in code is 481920.

        It expires in 10 minutes and can be used once. If you did not try to sign in, you can ignore this email — nobody can use the code without also having access to this inbox.

        This message was sent by Vidlix. Please do not reply: replies to this address are not read. To reach a person, use the conversation in your Vidlix inbox.
        TEXT;
    }
}
