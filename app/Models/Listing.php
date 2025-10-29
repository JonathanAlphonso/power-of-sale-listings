<?php

namespace App\Models;

use App\Models\Concerns\InteractsWithListingPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    /** @use HasFactory<\Database\Factories\ListingFactory> */
    use HasFactory;

    use InteractsWithListingPayload;
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'source_id',
        'municipality_id',
        'external_id',
        'board_code',
        'mls_number',
        'status_code',
        'display_status',
        'availability',
        'property_class',
        'property_type',
        'property_style',
        'sale_type',
        'currency',
        'street_number',
        'street_name',
        'street_address',
        'unit_number',
        'city',
        'district',
        'neighbourhood',
        'postal_code',
        'province',
        'latitude',
        'longitude',
        'days_on_market',
        'bedrooms',
        'bedrooms_possible',
        'bathrooms',
        'square_feet',
        'square_feet_text',
        'list_price',
        'original_list_price',
        'price',
        'price_low',
        'price_per_square_foot',
        'price_change',
        'price_change_direction',
        'is_address_public',
        'parcel_id',
        'modified_at',
        'payload',
        'ingestion_batch_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'bathrooms' => 'decimal:1',
        'is_address_public' => 'boolean',
        'latitude' => 'decimal:7',
        'list_price' => 'decimal:2',
        'longitude' => 'decimal:7',
        'modified_at' => 'datetime',
        'original_list_price' => 'decimal:2',
        'payload' => 'array',
        'price' => 'decimal:2',
        'price_low' => 'decimal:2',
        'price_per_square_foot' => 'decimal:2',
    ];

    public function media(): HasMany
    {
        return $this->hasMany(ListingMedia::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<Source, Listing>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return BelongsTo<Municipality, Listing>
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * @return HasMany<ListingStatusHistory>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ListingStatusHistory::class)->orderByDesc('changed_at');
    }

    /**
     * Scope queries to exclude rental listings from result sets.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithoutRentals(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder
                ->where('sale_type', '!=', 'RENT')
                ->orWhereNull('sale_type');
        });
    }

    // Payload handling helpers moved to InteractsWithListingPayload trait.
}
