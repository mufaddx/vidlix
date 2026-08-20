<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A hostname somebody has pointed at their public contact form.
 *
 * Only ACTIVE is served. Every other state is a stage on the way there or a
 * reason it stopped, and the difference matters: a domain that resolves but has
 * no certificate is not "nearly active", it is a browser warning.
 */
class CustomDomain extends Model
{
    public const NOT_CONNECTED = 'not_connected';

    public const PENDING_VERIFICATION = 'pending_verification';

    public const DNS_REQUIRED = 'dns_required';

    public const OWNERSHIP_PENDING = 'ownership_pending';

    public const SSL_PROVISIONING = 'ssl_provisioning';

    public const ACTIVE = 'active';

    public const FAILED = 'failed';

    public const SUSPENDED = 'suspended';

    public const DISCONNECTED = 'disconnected';

    protected $fillable = [
        'user_id', 'owner_scope', 'hostname', 'status', 'verification_token',
        'dns_target', 'dns_verified_at', 'ownership_verified_at', 'ssl_issued_at',
        'last_checked_at', 'provider', 'provider_hostname_id', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'dns_verified_at' => 'datetime',
            'ownership_verified_at' => 'datetime',
            'ssl_issued_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CustomDomainEvent::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    /** How far along the setup is, for the progress list on the settings page. */
    public function completedSteps(): array
    {
        return [
            'dns' => $this->dns_verified_at !== null,
            'ownership' => $this->ownership_verified_at !== null,
            'ssl' => $this->ssl_issued_at !== null,
        ];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::NOT_CONNECTED => __('Not connected'),
            self::PENDING_VERIFICATION => __('Pending verification'),
            self::DNS_REQUIRED => __('DNS configuration required'),
            self::OWNERSHIP_PENDING => __('Ownership verification pending'),
            self::SSL_PROVISIONING => __('SSL provisioning'),
            self::ACTIVE => __('Active'),
            self::FAILED => __('Failed'),
            self::SUSPENDED => __('Suspended'),
            self::DISCONNECTED => __('Disconnected'),
            default => $this->status,
        };
    }
}
