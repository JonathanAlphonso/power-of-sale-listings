<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'suspended_at' => 'datetime',
            'invited_at' => 'datetime',
            'password_forced_at' => 'datetime',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * @return HasMany<SavedSearch>
     */
    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    /**
     * @return BelongsTo<User, User>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'invited_by_id');
    }

    /**
     * @return BelongsTo<User, User>
     */
    public function passwordForcedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'password_forced_by_id');
    }

    public function scopeAdmins(Builder $builder): Builder
    {
        return $builder->where('role', UserRole::Admin);
    }

    /**
     * @return HasMany<ListingSuppression>
     */
    public function listingSuppressions(): HasMany
    {
        return $this->hasMany(ListingSuppression::class);
    }

    public function scopeActive(Builder $builder): Builder
    {
        return $builder->whereNull('suspended_at');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSubscriber(): bool
    {
        return $this->role === UserRole::Subscriber;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }
}
