<?php

namespace App\Services\Profiles;

use App\Models\EditorProfile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\Notifier;
use Illuminate\Validation\ValidationException;

/**
 * Applying to be listed as an editor, and being reviewed.
 *
 * The rule the whole class exists to hold: **accepting the terms is not
 * approval, and neither is submitting.** Clicking Editor, filling the form and
 * ticking the box all leave the profile invisible. A person reads it and
 * decides, and only that decision makes the profile appear in the marketplace.
 *
 * Getting this wrong is not a cosmetic bug. It would put unvetted people in
 * front of brands with Vidlix's implicit endorsement.
 */
class EditorApplication
{
    public function __construct(
        private AuditLogger $audit,
        private Notifier $notifier,
    ) {}

    /**
     * Save the application without sending it.
     *
     * @param  array<string, mixed>  $input
     */
    public function saveDraft(EditorProfile $profile, array $input): EditorProfile
    {
        $this->assertEditable($profile);

        $profile->fill([
            'display_name' => $this->text($input['display_name'] ?? $profile->display_name, 120),
            'bio' => $this->text($input['bio'] ?? null, 3000),
            'years_experience' => isset($input['years_experience']) && $input['years_experience'] !== ''
                ? min(70, max(0, (int) $input['years_experience']))
                : null,
            'specializations' => $this->list($input['specializations'] ?? null),
            'software' => $this->list($input['software'] ?? null),
            'services' => $this->list($input['services'] ?? null),
            'languages' => $this->list($input['languages'] ?? null),
            'starting_price_minor' => isset($input['starting_price_minor']) && $input['starting_price_minor'] !== ''
                ? max(0, (int) $input['starting_price_minor'])
                : null,
            'availability' => $this->text($input['availability'] ?? null, 120),
            'location' => $this->text($input['location'] ?? null, 160),
            'portfolio_url' => $this->url($input['portfolio_url'] ?? null),
        ]);

        // A draft that has never been sent stays a draft; one that came back for
        // more information keeps that status until it is resent, so the editor
        // can still see why it came back.
        if ($profile->application_status === EditorProfile::NOT_APPLIED) {
            $profile->application_status = EditorProfile::DRAFT;
        }

        $profile->save();

        return $profile;
    }

    /**
     * Accept the terms.
     *
     * Recorded with a timestamp because "did they agree, and when" is a
     * question that gets asked long afterwards and cannot be reconstructed.
     */
    public function acceptTerms(EditorProfile $profile): void
    {
        $this->assertEditable($profile);

        $profile->update(['terms_accepted_at' => now()]);

        $this->audit->record('editor.terms_accepted', $profile, [], $profile->user_id);
    }

    /** Send it for review. */
    public function submit(EditorProfile $profile): EditorProfile
    {
        $this->assertEditable($profile);

        $missing = $profile->missingForSubmission();

        if ($missing !== []) {
            // Names what is missing rather than saying "incomplete", so nobody
            // has to hunt for the field.
            throw ValidationException::withMessages([
                'application' => __('Still needed: :list.', ['list' => implode(', ', $missing)]),
            ]);
        }

        $profile->update([
            'application_status' => EditorProfile::SUBMITTED,
            'submitted_at' => now(),
            // Cleared on resubmission: the old note described the previous
            // version and would read as a fresh complaint about this one.
            'review_note' => null,
        ]);

        $this->audit->record('editor.submitted', $profile, [], $profile->user_id);

        return $profile;
    }

    /** A reviewer has picked it up. Visible to the applicant, so they can stop wondering. */
    public function beginReview(EditorProfile $profile, User $reviewer): EditorProfile
    {
        if (! $profile->isAwaitingDecision()) {
            throw ValidationException::withMessages([
                'application' => __('That application is not waiting for a decision.'),
            ]);
        }

        $profile->update([
            'application_status' => EditorProfile::UNDER_REVIEW,
            'reviewed_by_user_id' => $reviewer->id,
        ]);

        return $profile;
    }

    /**
     * Decide.
     *
     * Approval is the only thing that makes an editor visible, and it is
     * deliberately the only place `visibility` is turned on.
     */
    public function decide(EditorProfile $profile, User $reviewer, string $decision, ?string $note = null): EditorProfile
    {
        $permitted = [
            EditorProfile::APPROVED,
            EditorProfile::REJECTED,
            EditorProfile::MORE_INFO,
            EditorProfile::SUSPENDED,
        ];

        if (! in_array($decision, $permitted, true)) {
            throw ValidationException::withMessages(['decision' => __('That is not a decision.')]);
        }

        if (in_array($decision, [EditorProfile::REJECTED, EditorProfile::MORE_INFO], true) && blank($note)) {
            // A rejection with no reason is one the applicant cannot act on,
            // and it wastes the next round for both sides.
            throw ValidationException::withMessages([
                'note' => __('Say why, so they can do something about it.'),
            ]);
        }

        $profile->update([
            'application_status' => $decision,
            'review_note' => $note,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $reviewer->id,
            // Approval is the only path to visibility; every other decision
            // takes it away again.
            'visibility' => $decision === EditorProfile::APPROVED ? 'public' : 'private',
        ]);

        $this->audit->record('editor.decided', $profile, [
            'decision' => $decision,
            'note' => $note,
        ], $reviewer->id);

        $this->notifyApplicant($profile, $decision, $note);

        return $profile;
    }

    private function notifyApplicant(EditorProfile $profile, string $decision, ?string $note): void
    {
        $user = $profile->user;

        if ($user === null) {
            return;
        }

        [$title, $body] = match ($decision) {
            EditorProfile::APPROVED => [
                __('You are listed'),
                __('Your editor profile is approved and now appears in the marketplace.'),
            ],
            EditorProfile::MORE_INFO => [
                __('We need a little more'),
                $note ?: __('A reviewer needs something else before deciding.'),
            ],
            EditorProfile::REJECTED => [
                __('Not approved this time'),
                // Says it is not final, because it is not.
                ($note ?: __('Your application was not approved.')).' '.__('You can change it and apply again.'),
            ],
            default => [
                __('Your editor profile is suspended'),
                $note ?: __('Your editor profile has been suspended.'),
            ],
        };

        $this->notifier->send($user, 'verification', $title, $body, [
            'profile_type' => 'editor',
        ]);
    }

    private function assertEditable(EditorProfile $profile): void
    {
        if (! $profile->isEditable()) {
            throw ValidationException::withMessages([
                'application' => $profile->isAwaitingDecision()
                    ? __('Your application is with a reviewer. You can change it again if they come back to you.')
                    : __('This application can no longer be changed.'),
            ]);
        }
    }

    /**
     * One per line or comma-separated, which is how people type a list.
     *
     * @return list<string>
     */
    private function list(mixed $input): array
    {
        if (is_array($input)) {
            $parts = $input;
        } else {
            $parts = preg_split('/\r\n|\r|\n|,/', (string) $input) ?: [];
        }

        $items = [];

        foreach ($parts as $part) {
            if (! is_scalar($part)) {
                continue;
            }

            $value = trim(strip_tags((string) $part));

            if ($value !== '' && ! in_array($value, $items, true)) {
                $items[] = mb_substr($value, 0, 80);
            }
        }

        return array_slice($items, 0, 30);
    }

    private function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim(strip_tags($value));

        return $clean === '' ? null : mb_substr($clean, 0, $max);
    }

    private function url(mixed $value): ?string
    {
        $url = $this->text($value, 2000);

        return $url !== null && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }
}
