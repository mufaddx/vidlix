<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'mobile',
    'password',
    'status',
    'timezone',
    'locale',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * @return list<string>
     */
    public function roleSlugs(): array
    {
        return $this->roles()->pluck('slug')->all();
    }

    public function hasRole(string $slug): bool
    {
        return in_array($slug, $this->roleSlugs(), true);
    }

    public function creatorProfile(): HasOne
    {
        return $this->hasOne(CreatorProfile::class);
    }

    public function editorProfile(): HasOne
    {
        return $this->hasOne(EditorProfile::class);
    }

    public function brandProfile(): HasOne
    {
        return $this->hasOne(BrandProfile::class);
    }

    public function managerProfile(): HasOne
    {
        return $this->hasOne(ManagerProfile::class);
    }

    /** Accounts this user manages on somebody else's behalf. */
    public function managerAssignments(): HasMany
    {
        return $this->hasMany(ManagerAssignment::class, 'manager_user_id');
    }

    /** Managers appointed over this user's own accounts. */
    public function managedByAssignments(): HasMany
    {
        return $this->hasMany(ManagerAssignment::class, 'owner_user_id');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /** A super admin holds every ability implicitly; everyone else is granted them one by one. */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function hasAbility(string $ability): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return (bool) $this->employee?->can($ability);
    }

    /** True for anyone who should see the admin panel at all. */
    public function isStaff(): bool
    {
        return $this->isSuperAdmin() || $this->employee()->where('status', 'active')->exists();
    }

    public function ledgerAccounts(): HasMany
    {
        return $this->hasMany(LedgerAccount::class);
    }

    public function availableLedgerMinor(string $kind = 'earnings'): int
    {
        $account = $this->ledgerAccounts()->where('kind', $kind)->first();
        if ($account === null) {
            return 0;
        }

        return (int) LedgerEntry::query()
            ->where('ledger_account_id', $account->id)
            ->where('state', 'available')
            ->sum('amount_minor');
    }
}
