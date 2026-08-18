<?php

namespace App\Services\Integrations\Email;

use App\Contracts\EmailProviderInterface;
use App\Models\Message;
use Illuminate\Mail\Message as MailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * SMTP relay (Amazon SES SMTP, Postmark SMTP, or any authenticated MTA).
 *
 * The transport either hands the message to the relay or throws, so "accepted"
 * here means the relay took it. Delivery and bounces still come from the
 * provider event webhook.
 */
class SmtpEmailProvider implements EmailProviderInterface
{
    public function name(): string
    {
        return 'smtp';
    }

    public function isConfigured(): bool
    {
        $mailer = (string) config('mail.default');

        return $mailer !== 'log'
            && $mailer !== 'array'
            && filled(config('vidlix.email.from_address'))
            && filled(config('mail.mailers.'.$mailer.'.host', config('mail.mailers.'.$mailer.'.transport')));
    }

    public function sendThreadReply(Message $message, string $toEmail, string $replyTo): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'provider_not_configured',
                'provider_message_id' => null,
                'detail' => 'MAIL_* is still on the log/array mailer. The message is stored but not sent.',
            ];
        }

        $conversation = $message->conversation;
        $subject = filled($conversation?->subject) ? (string) $conversation->subject : 'Vidlix message';
        $domain = (string) (config('vidlix.email.inbound_domain') ?: parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'vidlix.local');
        $messageId = Str::uuid().'@'.$domain;

        try {
            Mail::raw((string) $message->body, function (MailMessage $mail) use ($toEmail, $replyTo, $subject, $message, $messageId) {
                $mail->to($toEmail)
                    ->subject($subject)
                    ->replyTo($replyTo)
                    ->from(
                        (string) config('vidlix.email.from_address'),
                        (string) config('vidlix.email.from_name'),
                    );

                $headers = $mail->getSymfonyMessage()->getHeaders();
                $headers->addIdHeader('Message-ID', $messageId);
                if (filled($message->in_reply_to)) {
                    $headers->addTextHeader('In-Reply-To', (string) $message->in_reply_to);
                    $headers->addTextHeader('References', (string) $message->in_reply_to);
                }
            });
        } catch (Throwable $e) {
            Log::warning('smtp.send.failure', ['message' => $e->getMessage()]);

            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'detail' => 'The SMTP relay rejected the message: '.$e->getMessage(),
            ];
        }

        return [
            'status' => 'accepted',
            'provider_message_id' => $messageId,
            'detail' => 'The relay accepted the message. Delivery is confirmed by the provider event webhook only.',
        ];
    }
}
