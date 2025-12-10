<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFavorite extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'listing_id',
        'notes',
    ];

    /**
     * @return BelongsTo<User, UserFavorite>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Listing, UserFavorite>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
