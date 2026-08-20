<?php

namespace App\Services\Notifications;

use App\Contracts\PushProviderInterface;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\DeviceToken;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Platform\Features;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tells a member something happened.
 *
 * Every notification is recorded in the database whether or not a device or a
 * provider was there to receive it, so the bell in the app is complete even
 * when push is unconfigured. A push that could not be sent is never reported
 * as sent: the stored row says what actually happened to it.
 */
class Notifier
{
    public function __construct(
        private PushProviderInterface $push,
        private Features $features,
    ) {}

    /**
     * @param  array<string, string>  $data  payload the app uses to deep-link
     * @return array{stored: bool, push: string}
     */
    public function send(User $user, string $event, string $title, string $body, array $data = []): array
    {
        $this->store($user, $event, $title, $body, $data);

        if (! $this->wants($user, $event, 'push')) {
            return ['stored' => true, 'push' => 'declined_by_member'];
        }

        if (! $this->features->enabled('push_notifications', $user)) {
            return ['stored' => true, 'push' => 'feature_off'];
        }

        if (! $this->push->isConfigured()) {
            // Stated rather than swallowed. An operator reading this knows the
            // member was not reached, instead of assuming they were.
            return ['stored' => true, 'push' => 'provider_not_configured'];
        }

        $tokens = DeviceToken::query()
            ->where('user_id', $user->id)
            ->whereNull('failed_at')
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            return ['stored' => true, 'push' => 'no_devices'];
        }

        $result = $this->push->send($tokens, $title, $body, $data + ['event' => $event]);

        if ($result['failed'] > 0 && $result['sent'] === 0) {
            DeviceToken::query()
                ->where('user_id', $user->id)
                ->whereIn('token', $tokens)
                ->update(['failed_at' => now(), 'failure_reason' => mb_substr($result['detail'], 0, 191)]);
        }

        return ['stored' => true, 'push' => $result['status']];
    }

    /**
     * Tell somebody about a thread, unless they have muted it.
     *
     * Muting silences the notification and nothing else: the message is still
     * delivered, still stored, and still unread in the inbox. Someone who mutes
     * a noisy thread is asking to stop being interrupted by it, not to stop
     * receiving it.
     *
     * @param  array<string, string>  $data
     * @return array{stored: bool, push: string}
     */
    public function sendAbout(User $user, Conversation $conversation, string $event, string $title, string $body, array $data = []): array
    {
        $muted = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->whereNotNull('muted_at')
            ->exists();

        if ($muted) {
            return ['stored' => false, 'push' => 'muted_by_member'];
        }

        return $this->send($user, $event, $title, $body, $data + [
            'conversation_uuid' => (string) $conversation->conversation_uuid,
        ]);
    }

    /** Whether the member wants this event on this channel. */
    public function wants(User $user, string $event, string $channel): bool
    {
        $preference = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('event', $event)
            ->first();

        // No row means the product default, which is on. Shipping preferences
        // must not silently mute everybody who has never opened the screen.
        return $preference === null ? true : (bool) $preference->{$channel};
    }

    /** @return array<string, array{push: bool, email: bool}> */
    public function preferences(User $user): array
    {
        $stored = NotificationPreference::query()->where('user_id', $user->id)->get()->keyBy('event');

        $rows = [];

        foreach (NotificationPreference::EVENTS as $event => $label) {
            $rows[$event] = [
                'label' => $label,
                'push' => (bool) ($stored[$event]->push ?? true),
                'email' => (bool) ($stored[$event]->email ?? true),
            ];
        }

        return $rows;
    }

    /** @param  array<string, array<string, mixed>>  $input */
    public function savePreferences(User $user, array $input): void
    {
        DB::transaction(function () use ($user, $input) {
            foreach (array_keys(NotificationPreference::EVENTS) as $event) {
                NotificationPreference::query()->updateOrCreate(
                    ['user_id' => $user->id, 'event' => $event],
                    [
                        'push' => (bool) ($input[$event]['push'] ?? false),
                        'email' => (bool) ($input[$event]['email'] ?? false),
                    ],
                );
            }
        });
    }

    /**
     * The bell is written first and always.
     *
     * The table is Laravel's own notifications table, so the readable text
     * lives inside `data` rather than in columns of its own.
     */
    private function store(User $user, string $event, string $title, string $body, array $data): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $event,
            'data' => ['title' => $title, 'body' => $body, 'payload' => $data],
        ]);
    }
}
