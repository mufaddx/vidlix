<?php

namespace Tests\Support;

use App\Contracts\EmailProviderInterface;
use App\Models\Message;
use App\Services\Email\OutboundIdentity;

/**
 * A configured email provider that records instead of sending.
 *
 * Tests that need to read a one-time code have nowhere else to get it: the
 * stored hash cannot be reversed, which is the whole point. Swapping the
 * provider is the honest seam — the code still travels the real path through
 * SystemMailer, it simply lands here instead of at Resend.
 */
final class RecordingEmailProvider implements EmailProviderInterface
{
    /** @var array<int, array{to: string, subject: string, body: string}> */
    public static array $sent = [];

    public static function reset(): void
    {
        self::$sent = [];
    }

    /** The most recent six-digit code sent to an address, if any. */
    public static function codeFor(string $email): ?string
    {
        $email = strtolower($email);

        foreach (array_reverse(self::$sent) as $mail) {
            if (strtolower($mail['to']) !== $email) {
                continue;
            }
            if (preg_match('/\b(\d{6})\b/', $mail['body'], $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'recording';
    }

    public function sendThreadReply(Message $message, string $toEmail, OutboundIdentity $identity): array
    {
        self::$sent[] = [
            'to' => $toEmail,
            'subject' => (string) $message->conversation?->subject,
            'body' => (string) $message->body,
        ];

        return ['status' => 'accepted', 'provider_message_id' => 'rec-'.count(self::$sent), 'detail' => 'Recorded.'];
    }

    public function sendSystemMail(string $toEmail, string $subject, string $body, OutboundIdentity $identity): array
    {
        self::$sent[] = ['to' => $toEmail, 'subject' => $subject, 'body' => $body];

        return ['status' => 'accepted', 'provider_message_id' => 'rec-'.count(self::$sent), 'detail' => 'Recorded.'];
    }
}
