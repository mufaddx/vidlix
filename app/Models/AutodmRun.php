<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutodmRun extends Model
{
    public const RECEIVED = 'received';

    public const MATCHED = 'matched';

    public const QUEUED = 'queued';

    public const SENT = 'sent';

    /**
     * Not a failure. An action the provider does not permit did not go wrong —
     * it was never allowed, and calling it a failure invites a retry that can
     * never succeed.
     */
    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    public const RETRY_SCHEDULED = 'retry_scheduled';

    public const PERMANENTLY_FAILED = 'permanently_failed';

    public const PUBLIC_REPLY = 'public_reply';

    public const PRIVATE_REPLY = 'private_reply';

    protected $fillable = [
        'autodm_automation_id', 'autodm_automation_version_id', 'autodm_event_id',
        'action', 'status', 'reason_code', 'detail', 'provider_response_id',
        'attempts', 'next_attempt_at',
    ];

    protected function casts(): array
    {
        return ['next_attempt_at' => 'datetime'];
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(AutodmAutomation::class, 'autodm_automation_id');
    }

    public function succeeded(): bool
    {
        return $this->status === self::SENT;
    }
}
