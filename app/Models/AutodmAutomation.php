<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutodmAutomation extends Model
{
    public const DRAFT = 'draft';

    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    protected $fillable = [
        'uuid', 'user_id', 'instagram_account_id', 'instagram_media_id',
        'name', 'status', 'active_version_id', 'activated_at', 'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AutodmAutomationVersion::class, 'autodm_automation_id')
            ->orderBy('version_number');
    }

    public function activeVersion(): ?AutodmAutomationVersion
    {
        return $this->active_version_id
            ? AutodmAutomationVersion::query()->find($this->active_version_id)
            : null;
    }

    /** The newest version, whether or not it is the one running. */
    public function draftVersion(): ?AutodmAutomationVersion
    {
        return AutodmAutomationVersion::query()
            ->where('autodm_automation_id', $this->getKey())
            ->orderByDesc('version_number')
            ->first();
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutodmRun::class, 'autodm_automation_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(InstagramAccount::class, 'instagram_account_id');
    }

    public function medium(): BelongsTo
    {
        return $this->belongsTo(InstagramMedium::class, 'instagram_media_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE && $this->active_version_id !== null;
    }
}
