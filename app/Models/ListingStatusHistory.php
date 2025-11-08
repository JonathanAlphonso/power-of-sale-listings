<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingStatusHistory extends Model
{
    /** @use HasFactory<\Database\Factories\ListingStatusHistoryFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'listing_id',
        'source_id',
        'status_code',
        'status_label',
        'notes',
        'changed_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Listing, ListingStatusHistory>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * @return BelongsTo<Source, ListingStatusHistory>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
