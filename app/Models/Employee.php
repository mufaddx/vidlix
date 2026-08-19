<?php

namespace App\Models;

use App\Support\Ability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Employee extends Model
{
    protected $fillable = [
        'user_id', 'employee_code', 'title', 'status',
        'created_by_user_id', 'joined_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function abilities(): HasMany
    {
        return $this->hasMany(EmployeeAbility::class);
    }

    /** A suspended employee keeps their grants on file but can use none of them. */
    public function can(string $ability): bool
    {
        return $this->status === 'active'
            && $this->abilities()->where('ability', $ability)->exists();
    }

    /** @return list<string> */
    public function abilityList(): array
    {
        return $this->abilities()->pluck('ability')->all();
    }

    public static function generateCode(): string
    {
        do {
            $code = 'VX-'.now()->format('y').'-'.Str::upper(Str::random(5));
        } while (self::query()->where('employee_code', $code)->exists());

        return $code;
    }

    /** @return list<string> */
    public static function grantableAbilities(): array
    {
        return Ability::grantable();
    }
}
