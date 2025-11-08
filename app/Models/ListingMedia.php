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
        'stored_disk',
        'stored_path',
        'stored_at',
        'variants',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'meta' => 'array',
            'position' => 'integer',
            'stored_at' => 'datetime',
            'variants' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Listing, ListingMedia>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function getPublicUrlAttribute(): ?string
    {
        if (is_string($this->stored_disk) && $this->stored_disk !== '' && is_string($this->stored_path) && $this->stored_path !== '') {
            try {
                return \Illuminate\Support\Facades\Storage::disk($this->stored_disk)->url($this->stored_path);
            } catch (\Throwable) {
                // fall through to remote URLs
            }
        }

        return $this->preview_url ?: $this->url;
    }
}
