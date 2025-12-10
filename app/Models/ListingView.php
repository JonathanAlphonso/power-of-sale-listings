<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingView extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'listing_id',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, ListingView>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Listing, ListingView>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public static function recordView(User $user, Listing $listing): self
    {
        return self::updateOrCreate(
            [
                'user_id' => $user->id,
                'listing_id' => $listing->id,
            ],
            [
                'viewed_at' => now(),
            ]
        );
    }
}
