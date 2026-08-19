<?php

namespace App\Services\Email;

use Illuminate\Http\Request;

/**
 * Flattens the inbound payload shapes of the supported providers into one
 * structure. Routing is by explicit token only: if no plus-address token
 * resolves, the mail is left unmatched rather than guessed into someone's inbox.
 */
class InboundEmailNormalizer
{
    /**
     * @return array{provider_event_id: ?string, from_email: ?string, from_name: ?string, subject: ?string, text: string, message_id: ?string, in_reply_to: ?string, recipients: list<string>, routing_token: ?string}
     */
    public function normalize(Request $request): array
    {
        $payload = $this->payload($request);

        $recipients = $this->recipients($payload);
        $headers = $this->headerMap($payload);

        $fromEmail = $this->extractAddress($this->first($payload, [
            'FromFull.Email', 'from', 'From', 'sender', 'envelope.from',
        ]));

        return [
            'provider_event_id' => $this->first($payload, ['id', 'MessageID', 'message_id', 'sg_message_id']),
            'from_email' => $fromEmail,
            'from_name' => $this->first($payload, ['FromFull.Name', 'from_name']),
            'subject' => $this->first($payload, ['subject', 'Subject']),
            'text' => (string) ($this->first($payload, ['text', 'TextBody', 'body', 'StrippedTextReply', 'plain']) ?? ''),
            'message_id' => $this->first($payload, ['message_id', 'MessageID'])
                ?? ($headers['message-id'] ?? null),
            'in_reply_to' => $this->first($payload, ['in_reply_to'])
                ?? ($headers['in-reply-to'] ?? null),
            'recipients' => $recipients,
            'routing_token' => $this->routingToken($payload, $recipients),
        ];
    }

    private function payload(Request $request): array
    {
        $decoded = json_decode((string) $request->getContent(), true);

        return is_array($decoded) ? $decoded : $request->all();
    }

    /** @return list<string> */
    private function recipients(array $payload): array
    {
        $found = [];

        foreach (['to', 'To', 'OriginalRecipient', 'recipient'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $found = array_merge($found, preg_split('/\s*,\s*/', $value) ?: []);
            }
        }

        foreach ((array) ($payload['ToFull'] ?? []) as $entry) {
            if (is_array($entry) && filled($entry['Email'] ?? null)) {
                $found[] = (string) $entry['Email'];
            }
        }

        // SendGrid Inbound Parse ships the SMTP envelope as a JSON string.
        $envelope = $payload['envelope'] ?? null;
        if (is_string($envelope)) {
            $envelope = json_decode($envelope, true);
        }
        foreach ((array) ($envelope['to'] ?? []) as $entry) {
            if (is_string($entry)) {
                $found[] = $entry;
            }
        }

        $addresses = [];
        foreach ($found as $candidate) {
            $address = $this->extractAddress($candidate);
            if ($address !== null) {
                $addresses[strtolower($address)] = strtolower($address);
            }
        }

        return array_values($addresses);
    }

    /** @return array<string, string> lower-cased header name => value */
    private function headerMap(array $payload): array
    {
        $map = [];

        foreach ((array) ($payload['Headers'] ?? []) as $header) {
            if (is_array($header) && filled($header['Name'] ?? null)) {
                $map[strtolower((string) $header['Name'])] = (string) ($header['Value'] ?? '');
            }
        }

        // SendGrid posts the raw header block as one string.
        $raw = $payload['headers'] ?? null;
        if (is_string($raw)) {
            foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
                if (preg_match('/^([A-Za-z0-9\-]+):\s*(.*)$/', $line, $m) === 1) {
                    $map[strtolower($m[1])] = trim($m[2]);
                }
            }
        }

        return $map;
    }

    private function routingToken(array $payload, array $recipients): ?string
    {
        $explicit = $payload['routing_token'] ?? null;
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        foreach ((array) ($payload['ToFull'] ?? []) as $entry) {
            if (is_array($entry) && filled($entry['MailboxHash'] ?? null)) {
                return (string) $entry['MailboxHash'];
            }
        }

        // Threads leave from creator@ / editor@ as well as the neutral reply@,
        // so any of those mailboxes may carry a routing token back.
        $prefixes = array_filter([
            strtolower((string) config('vidlix.email.reply_prefix', 'reply')),
            'creator',
            'editor',
        ]);

        foreach ($recipients as $address) {
            [$local] = array_pad(explode('@', $address, 2), 2, '');
            if (! str_contains($local, '+')) {
                continue;
            }
            [$mailbox, $token] = explode('+', $local, 2);
            if ($token !== '' && in_array($mailbox, $prefixes, true)) {
                return $token;
            }
        }

        return null;
    }

    private function first(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function extractAddress(?string $value): ?string
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
}
