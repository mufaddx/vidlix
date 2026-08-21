# Inbox

One inbox for every conversation a person is part of, whatever role they hold.

## Sources

| Source | Channel | State |
|---|---|---|
| Internal chat | `internal` | Working |
| Public inquiry / email | `external_email` | Working |
| Support | `support` | Separate — admin help desk, not a member inbox |
| Instagram | — | Not implemented as an inbox source |
| WhatsApp | — | Not configured; not shown |

## Access

`App\Policies\ConversationPolicy` — ownership or participation, nothing else.
Support threads are excluded there rather than filtered later, because a support
thread has its own screen with its own abilities.

Refusals are **404, not 403**. A 403 confirms the thread exists, which is itself
a disclosure.

## Filters

`All · Creator · Editor · Brand`, filtering on the marketplace role of the
**other** participants. The same thread appears under Brand for the creator and
under Creator for the brand — one column on the conversation could only ever be
right for one of them, which is why the role lives on the participant.

A participant with no marketplace role is left blank rather than guessed at. A
guess behind a filter people rely on is worse than a gap.

## Ordering

Your own kind of work first: creator threads on top for a creator, editor
threads for an editor, then the rest in a fixed order so the list does not
reshuffle between visits.

Done in SQL, not after paging — sorting a page only sorts whatever happened to
land on it.

## Read state

`conversation_participants.last_read_at`, per participant. The same thread is
unread for one side and read for the other.

## Controls

All four sit on the participant, for the same reason read state does — one side
filing a thread away says nothing about the other side.

| Control | What it does |
|---|---|
| Archive | Hides it from your list. Reversible. Yours alone. |
| Mute | Stops the notification. The message still arrives, is still stored, is still unread. |
| Report | One per person per thread. Goes to the admin moderation queue. |
| Block | Stops a **new** thread starting, both directions. Leaves existing threads alone. |

Muting silences interruption, not delivery — somebody muting a noisy thread is
not asking to stop receiving it.

Blocking deliberately does not delete history: that history is often the
evidence of why the block was needed.

Reporting is capped at one row per person per thread. A second report is the
same complaint again, and duplicates bury a queue rather than fill it.

## Moderation

`/admin/reports`, gated on `support.view` to read and `support.reply` to decide.
Open reports first, oldest first within that, because the complaint that has
waited longest is the one most likely to have been forgotten. Every decision is
audited with who made it.

The thread itself is not shown in the queue. Reading a member's messages is a
separate decision from triaging a complaint about them.

## Delivery status

External threads show what the provider reported — nothing says "sent" because
a reply was submitted. See `docs/EMAIL-THREADING.md`.

## Code

| Thing | Where |
|---|---|
| Query, filters, ordering | `App\Services\Messaging\InboxQuery` |
| Controller | `App\Http\Controllers\App\InboxController` |
| Policy | `App\Policies\ConversationPolicy` |
| Mute-aware notify | `App\Services\Notifications\Notifier::sendAbout()` |

## Testing

`tests/Feature/InboxControlsTest.php`, `UnifiedInboxTest.php`,
`AuthorizationTest.php`.
