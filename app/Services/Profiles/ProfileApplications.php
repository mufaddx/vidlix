<?php

namespace App\Services\Profiles;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Identity\AccountProvisioner;
use App\Services\Notifications\Notifier;
use Illuminate\Validation\ValidationException;

/**
 * Applying for a profile, and a reviewer deciding on it.
 *
 * Applying is not becoming. An editor or a brand profile is created in
 * `pending_review` and stays unusable until somebody approves it; before this,
 * applying attached the role and the profile was live immediately, which made
 * the whole admin review ceremonial.
 */
class ProfileApplications
{
    public function __construct(
        private AccountProvisioner $provisioner,
        private ProfileDirectory $directory,
        private AuditLogger $audit,
        private Notifier $notifier,
    ) {}

    /**
     * @return array{status: string, message: string}
     */
    public function apply(User $user, string $type): array
    {
        if (! in_array($type, ProfileDirectory::TYPES, true)) {
            throw ValidationException::withMessages(['role' => __('That is not a profile you can apply for.')]);
        }

        $current = $this->directory->statusOf($user, $type);
        $label = ProfileDirectory::LABELS[$type];

        if ($current === ProfileDirectory::ACTIVE) {
            return ['status' => $current, 'message' => __('You already have a :label profile.', ['label' => $label])];
        }

        if ($current === ProfileDirectory::PENDING) {
            return ['status' => $current, 'message' => __('Your :label application is already with us.', ['label' => $label])];
        }

        if ($current === ProfileDirectory::SUSPENDED) {
            // Re-applying must not quietly lift a suspension somebody imposed.
            throw ValidationException::withMessages([
                'role' => __('That profile is suspended. Please contact support.'),
            ]);
        }

        // The role says the account has a profile of this kind; it does not
        // say the profile may be used. That is ProfileDirectory's answer, and
        // keeping the two separate is what makes review mean something while
        // the rest of the app can still ask "is there an editor profile here".
        $role = Role::query()->where('slug', $type)->first();

        if ($role !== null && ! in_array($type, $user->roleSlugs(), true)) {
            $user->roles()->attach($role);
        }

        // The row has to exist before it can be filled in and reviewed.
        $this->provisioner->provisionRole($user->fresh(), $type);

        $status = $this->markApplied($user->fresh(), $type);
        $this->audit->record('profile.applied', $user, ['profile' => $type, 'status' => $status]);

        return [
            'status' => $status,
            'message' => $status === ProfileDirectory::ACTIVE
                ? __('Your :label profile is ready.', ['label' => $label])
                : __('Your :label application has been sent for review.', ['label' => $label]),
        ];
    }

    /** A reviewer's decision. Returns the new status. */
    public function decide(User $user, string $type, string $decision): string
    {
        $label = ProfileDirectory::LABELS[$type] ?? $type;

        match ($type) {
            'editor' => $user->editorProfile()->update([
                'application_status' => match ($decision) {
                    'approve' => 'approved',
                    'reject' => 'rejected',
                    'suspend' => 'suspended',
                    default => 'pending_review',
                },
            ]),
            'brand' => $user->brandProfile()->update([
                'verification_status' => match ($decision) {
                    'approve' => 'verified',
                    'reject' => 'rejected',
                    'suspend' => 'suspended',
                    default => 'pending_review',
                },
            ]),
            'creator' => $user->creatorProfile()->update([
                'visibility' => $decision === 'suspend' ? 'suspended' : 'public',
            ]),
            default => null,
        };

        $status = $this->directory->statusOf($user->fresh(), $type);
        $this->audit->record('profile.decided', $user, ['profile' => $type, 'decision' => $decision]);

        $this->tell($user, $type, $label, $status);

        return $status;
    }

    /** The person is told what happened to their application, either way. */
    private function tell(User $user, string $type, string $label, string $status): void
    {
        [$title, $body] = match ($status) {
            ProfileDirectory::ACTIVE => [
                "Your {$label} profile has been approved",
                "You can now switch to {$label} from your profile.",
            ],
            ProfileDirectory::REJECTED => [
                "Your {$label} application was not approved",
                'Support can tell you what would need to change.',
            ],
            ProfileDirectory::SUSPENDED => [
                "Your {$label} profile has been suspended",
                'Your other profiles are unaffected.',
            ],
            default => ['', ''],
        };

        if ($title === '') {
            return;
        }

        $this->notifier->send($user, 'verification_decided', $title, $body, ['profile' => $type]);
    }

    /**
     * Put a freshly created profile into the state it should start in.
     *
     * @return string the resulting status
     */
    private function markApplied(User $user, string $type): string
    {
        match ($type) {
            // An influencer profile needs no review.
            'creator' => $user->creatorProfile()->update(['visibility' => 'public']),
            'editor' => $user->editorProfile()->update(['application_status' => 'pending_review']),
            'brand' => $user->brandProfile()->update(['verification_status' => 'pending_review']),
            default => null,
        };

        return $this->directory->statusOf($user->fresh(), $type);
    }
}
