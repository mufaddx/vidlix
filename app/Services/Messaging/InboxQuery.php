<?php

namespace App\Services\Messaging;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * One inbox for every conversation a person is part of.
 *
 * Threads used to be reachable only through the creator profile that owned
 * them, so an editor or a brand had nowhere to read their mail. This looks the
 * person up by participation and by ownership, which covers internal chat and
 * external email alike.
 *
 * Filtering is by the marketplace role of the *other* participants, so the same
 * thread appears under Brand for the creator and under Creator for the brand.
 */
class InboxQuery
{
    public const FILTERS = ['all', 'creator', 'editor', 'brand'];

    /**
     * @param  string  $filter  one of FILTERS — what the other side is
     * @param  bool  $archived  show what has been filed away instead of what has not
     */
    public function forUser(
        User $user,
        string $filter = 'all',
        ?string $search = null,
        int $perPage = 25,
        bool $archived = false,
    ): LengthAwarePaginator {
        return $this->base($user)
            ->when($this->normalise($filter) !== 'all', fn ($q) => $this->whereCounterpart($q, $user, $this->normalise($filter)))
            ->where(fn ($q) => $this->whereArchived($q, $user, $archived))
            ->when(filled($search), fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('subject', 'like', '%'.$search.'%')
                    ->orWhere('conversation_uuid', $search)
                    ->orWhereHas('externalContact', fn ($c) => $c->where('email', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%'));
            }))
            ->with(['externalContact', 'participants.user:id,name'])
            ->orderByRaw($this->rolePriorityOrder($user), $this->rolePriorityBindings($user))
            ->latest('last_message_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return array<string, int> counts per filter, for the tab labels */
    public function counts(User $user, bool $archived = false): array
    {
        $unfiled = fn () => $this->base($user)->where(fn ($q) => $this->whereArchived($q, $user, $archived));

        $counts = ['all' => $unfiled()->count()];

        foreach (['creator', 'editor', 'brand'] as $role) {
            $counts[$role] = $this->whereCounterpart($unfiled(), $user, $role)->count();
        }

        return $counts;
    }

    /**
     * The order a person's own kind of work comes first in.
     *
     * A creator opening the inbox wants creator threads at the top, then
     * editors, then brands; an editor wants the reverse of the first two. It is
     * done in SQL rather than after paging, because sorting a page is sorting
     * whatever happened to be on it.
     */
    private function rolePriorityOrder(User $user): string
    {
        return '(select min(case cp.marketplace_role when ? then 0 when ? then 1 when ? then 2 else 3 end)'
            .' from conversation_participants cp'
            .' where cp.conversation_id = conversations.id and cp.user_id != ?) asc';
    }

    /** @return list<string|int> */
    private function rolePriorityBindings(User $user): array
    {
        $own = $this->roleFor($user) ?? 'creator';

        // Their own kind first, then the other two in a fixed order so the list
        // does not reshuffle itself between visits.
        $rest = array_values(array_diff(['creator', 'editor', 'brand'], [$own]));

        return [$own, $rest[0], $rest[1], $user->id];
    }

    public function archive(User $user, Conversation $conversation, bool $archived): void
    {
        $this->participantRow($user, $conversation)?->update([
            'archived_at' => $archived ? now() : null,
        ]);
    }

    public function mute(User $user, Conversation $conversation, bool $muted): void
    {
        $this->participantRow($user, $conversation)?->update([
            'muted_at' => $muted ? now() : null,
        ]);
    }

    public function isArchived(User $user, Conversation $conversation): bool
    {
        return $this->participantRow($user, $conversation)?->archived_at !== null;
    }

    public function isMuted(User $user, Conversation $conversation): bool
    {
        return $this->participantRow($user, $conversation)?->muted_at !== null;
    }

    private function participantRow(User $user, Conversation $conversation): ?ConversationParticipant
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Archive state is the caller's own, so this looks at their participant row
     * only. A thread they own but were never added to counts as unarchived,
     * which is why the absence of a row is treated as "not filed away".
     */
    private function whereArchived(Builder $query, User $user, bool $archived): Builder
    {
        $rows = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->whereNotNull('archived_at')
            ->select('conversation_id');

        return $archived
            ? $query->whereIn('id', $rows)
            : $query->whereNotIn('id', $rows);
    }

    /**
     * How many messages the person has not opened yet, per conversation.
     *
     * @param  iterable<int, Conversation>  $conversations
     * @return array<int, int>
     */
    public function unreadCounts(User $user, iterable $conversations): array
    {
        $ids = collect($conversations)->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $lastRead = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->whereIn('conversation_id', $ids)
            ->pluck('last_read_at', 'conversation_id');

        $counts = [];

        foreach ($ids as $id) {
            $counts[$id] = Message::query()
                ->where('conversation_id', $id)
                // Their own messages are not unread mail.
                ->where(fn ($q) => $q->whereNull('actor_user_id')->orWhere('actor_user_id', '!=', $user->id))
                ->when($lastRead[$id] ?? null, fn ($q, $at) => $q->where('created_at', '>', $at))
                ->count();
        }

        return $counts;
    }

    public function markRead(User $user, Conversation $conversation): void
    {
        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);
    }

    /**
     * The marketplace role to record for a participant.
     *
     * Returns null when the person holds none of the three, rather than
     * defaulting to one: a blank is honest, a default is a guess that would sit
     * behind a filter people rely on.
     */
    public function roleFor(User $user): ?string
    {
        $roles = $user->roleSlugs();

        foreach (['brand', 'editor', 'creator'] as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return null;
    }

    /** Conversations this person owns or takes part in, minus the help desk. */
    private function base(User $user): Builder
    {
        return Conversation::query()
            ->where(function ($q) use ($user) {
                $q->where('owner_user_id', $user->id)
                    ->orWhereIn('id', ConversationParticipant::query()
                        ->where('user_id', $user->id)
                        ->select('conversation_id'));
            })
            // The help desk has its own screen in the admin panel; it is not
            // part of a member's inbox.
            ->where('channel', '!=', 'support');
    }

    private function whereCounterpart(Builder $query, User $user, string $role): Builder
    {
        return $query->whereHas('participants', fn ($p) => $p
            ->where('user_id', '!=', $user->id)
            ->where('marketplace_role', $role));
    }

    private function normalise(string $filter): string
    {
        return in_array($filter, self::FILTERS, true) ? $filter : 'all';
    }
}
