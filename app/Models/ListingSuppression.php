<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingSuppression extends Model
{
    /** @use HasFactory<\Database\Factories\ListingSuppressionFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'listing_id',
        'user_id',
        'release_user_id',
        'reason',
        'notes',
        'suppressed_at',
        'expires_at',
        'released_at',
        'release_reason',
        'release_notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
        'suppressed_at' => 'datetime',
    ];

    /**
     * Determine if the suppression is currently active.
     */
    public function isActive(): bool
    {
        if ($this->released_at !== null) {
            return false;
        }

        if ($this->expires_at === null) {
            return true;
        }

        return $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<Listing, ListingSuppression>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * @return BelongsTo<User, ListingSuppression>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, ListingSuppression>
     */
    public function releaseUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'release_user_id');
    }
}
