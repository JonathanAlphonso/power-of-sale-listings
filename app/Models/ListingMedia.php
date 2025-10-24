<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingMedia extends Model
{
    /** @use HasFactory<\Database\Factories\ListingMediaFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'listing_id',
        'media_type',
        'label',
        'position',
        'is_primary',
        'url',
        'preview_url',
        'variants',
        'meta',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'meta' => 'array',
        'position' => 'integer',
        'variants' => 'array',
    ];

    /**
     * @return BelongsTo<Listing, ListingMedia>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
