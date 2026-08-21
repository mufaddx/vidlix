<?php

namespace App\Models;

use App\Models\Concerns\RegistersUsername;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An editor's marketplace profile and the application behind it.
 *
 * Accepting the terms is not approval, and neither is submitting. The statuses
 * below are separate because they are separate answers: "we have it", "somebody
 * is reading it" and "we need something else" are three different things to be
 * told, and collapsing them means an applicant who cannot tell whether to wait
 * or to act.
 *
 * @property User|null $user
 * @property list<string>|null $services
 * @property list<string>|null $software
 * @property list<string>|null $specializations
 * @property list<string>|null $languages
 * @property string|null $review_note
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $terms_accepted_at
 */
class EditorProfile extends Model
{
    use RegistersUsername;

    /** Nothing has been sent yet. Editable, invisible to everyone else. */
    public const DRAFT = 'draft';

    public const NOT_APPLIED = 'not_applied';

    public const SUBMITTED = 'submitted';

    public const UNDER_REVIEW = 'under_review';

    /** A reviewer needs something specific. The note says what. */
    public const MORE_INFO = 'more_info';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const SUSPENDED = 'suspended';

    /** States in which the editor may still change their application. */
    public const EDITABLE = [self::NOT_APPLIED, self::DRAFT, self::MORE_INFO, self::REJECTED];

    protected $fillable = [
        'user_id', 'username', 'display_name', 'bio', 'application_status',
        'software', 'specializations', 'starting_price_minor', 'availability',
        'visibility', 'accepts_inquiries',
        'years_experience', 'services', 'location', 'languages', 'portfolio_url',
        'submitted_at', 'reviewed_at', 'reviewed_by_user_id', 'review_note',
        'terms_accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'software' => 'array',
            'specializations' => 'array',
            'services' => 'array',
            'languages' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
        ];
    }

    public function registryProfileType(): string
    {
        return 'editor';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isApproved(): bool
    {
        return $this->application_status === self::APPROVED;
    }

    /** Only an approved, public editor page is reachable by the world. */
    public function isPublished(): bool
    {
        return $this->visibility === 'public' && $this->isApproved();
    }

    public function isEditable(): bool
    {
        return in_array($this->application_status, self::EDITABLE, true);
    }

    public function isAwaitingDecision(): bool
    {
        return in_array($this->application_status, [self::SUBMITTED, self::UNDER_REVIEW], true);
    }

    /**
     * What is still missing before this can be sent.
     *
     * Returned as a list rather than a boolean so the interface can say which
     * field, instead of refusing with "incomplete" and leaving somebody to
     * hunt for it.
     *
     * @return list<string>
     */
    public function missingForSubmission(): array
    {
        $missing = [];

        if (blank($this->display_name)) {
            $missing[] = __('your name');
        }

        if (blank($this->bio)) {
            $missing[] = __('a short bio');
        }

        if (blank($this->specializations)) {
            $missing[] = __('what you specialise in');
        }

        if (blank($this->software)) {
            $missing[] = __('the software you use');
        }

        if (blank($this->services)) {
            $missing[] = __('the services you offer');
        }

        if ($this->starting_price_minor === null) {
            $missing[] = __('a starting price');
        }

        if (blank($this->availability)) {
            $missing[] = __('your availability');
        }

        if ($this->terms_accepted_at === null) {
            $missing[] = __('the terms');
        }

        return $missing;
    }

    public function statusLabel(): string
    {
        return match ($this->application_status) {
            self::NOT_APPLIED, self::DRAFT => __('Draft'),
            self::SUBMITTED => __('Submitted'),
            self::UNDER_REVIEW => __('Under review'),
            self::MORE_INFO => __('More information needed'),
            self::APPROVED => __('Approved'),
            self::REJECTED => __('Not approved'),
            self::SUSPENDED => __('Suspended'),
            default => $this->application_status,
        };
    }
}
