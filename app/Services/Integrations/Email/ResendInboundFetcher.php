<?php

namespace App\Services\Integrations\Email;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fetches the body of an inbound email from Resend.
 *
 * The email.received webhook carries metadata only — no body, no headers — so
 * the content has to be pulled separately. Resend keeps the message, so a
 * failure here is retryable rather than lost mail.
 */
class ResendInboundFetcher
{
    /**
     * @return array{provider_event_id: string, from_email: ?string, from_name: ?string, subject: ?string, text: string, message_id: ?string, in_reply_to: ?string, recipients: list<string>, routing_token: null}|null
     */
    public function fetch(string $emailId): ?array
    {
        if (! filled(config('vidlix.email.api_key'))) {
            return null;
        }

        try {
            $response = Http::withToken((string) config('vidlix.email.api_key'))
                ->baseUrl(rtrim((string) config('vidlix.email.api_base') ?: 'https://api.resend.com', '/'))
                ->acceptJson()
                ->timeout((int) config('vidlix.email.timeout', 20))
                ->get('/emails/receiving/'.$emailId);
        } catch (Throwable $e) {
            Log::warning('resend.inbound.transport_failure', ['message' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('resend.inbound.fetch_failed', [
                'email_id' => $emailId,
                'status' => $response->status(),
            ]);

            return null;
        }

        $body = (array) $response->json();
        $headers = (array) ($body['headers'] ?? []);

        return [
            'provider_event_id' => $emailId,
            'from_email' => $this->address($body['from'] ?? null),
            'from_name' => $this->displayName($body['from'] ?? null),
            'subject' => $body['subject'] ?? null,
            'text' => $this->plainText($body),
            'message_id' => $body['message_id'] ?? null,
            'in_reply_to' => $this->header($headers, 'in-reply-to'),
            'recipients' => $this->recipients($body),
            'routing_token' => null, // resolved by the normaliser from the recipients
        ];
    }

    /** Prefer the text part; fall back to a readable rendering of the HTML. */
    private function plainText(array $body): string
    {
        $text = $body['text'] ?? null;
        if (is_string($text) && trim($text) !== '') {
            return $text;
        }

        $html = (string) ($body['html'] ?? '');
        if ($html === '') {
            return '';
        }

        $withBreaks = preg_replace('/<(br|\/p|\/div|\/tr)[^>]*>/i', "\n", $html) ?? $html;

        return trim(html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** @return list<string> */
    private function recipients(array $body): array
    {
        $found = [];
        foreach (['received_for', 'to', 'cc'] as $key) {
            foreach ((array) ($body[$key] ?? []) as $entry) {
                $address = $this->address(is_string($entry) ? $entry : null);
                if ($address !== null) {
                    $found[strtolower($address)] = strtolower($address);
                }
            }
        }

        return array_values($found);
    }

    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name && is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function address(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        if (preg_match('/<([^>]+)>/', $value, $m) === 1) {
            $value = $m[1];
        }
        $value = trim($value);

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    private function displayName(?string $value): ?string
    {
        if ($value === null || ! str_contains($value, '<')) {
            return null;
        }
        $name = trim(Str::before($value, '<'), " \t\"'");

        return $name !== '' ? $name : null;
    }
}
