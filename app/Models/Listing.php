<?php

namespace App\Models;

use App\Models\Concerns\InteractsWithListingPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Listing extends Model
{
    /** @use HasFactory<\Database\Factories\ListingFactory> */
    use HasFactory;

    use InteractsWithListingPayload;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::created(function (Listing $listing): void {
            if (! $listing->slug) {
                $listing->slug = $listing->generateSlug();
                $listing->saveQuietly();
            }
        });

        static::updating(function (Listing $listing): void {
            if ($listing->isDirty(['street_number', 'street_name', 'unit_number', 'city'])) {
                $listing->slug = $listing->generateSlug();
            }
        });
    }

    public function generateSlug(): string
    {
        $parts = [];

        // Unit number comes first (like HouseSigma: 208-236-albion-rd)
        // Extract just the numeric portion from unit numbers like "Suite 513" or "Apt. 123"
        if ($this->unit_number) {
            $unitNumber = preg_replace('/[^0-9]/', '', $this->unit_number);
            if ($unitNumber !== '') {
                $parts[] = $unitNumber;
            }
        }

        if ($this->street_number) {
            $parts[] = $this->street_number;
        }

        if ($this->street_name) {
            $parts[] = $this->street_name;
        }

        if ($this->city) {
            $parts[] = $this->city;
        }

        $baseSlug = Str::slug(implode(' ', $parts));

        if (empty($baseSlug)) {
            $baseSlug = 'listing';
        }

        return $baseSlug;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /**
     * Resolve the route binding for the {listing} parameter.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->find($value);
    }

    /**
     * Get the URL for this listing.
     */
    public function getUrlAttribute(): string
    {
        return route('listings.show', [
            'slug' => $this->slug ?? 'listing',
            'listing' => $this->id,
        ]);
    }

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'slug',
        'source_id',
        'municipality_id',
        'external_id',
        'listing_key',
        'board_code',
        'mls_number',
        'status_code',
        'display_status',
        'transaction_type',
        'availability',
        'property_class',
        'property_type',
        'property_style',
        'currency',
        'street_number',
        'street_name',
        'street_address',
        'public_remarks',
        'unit_number',
        'city',
        'district',
        'neighbourhood',
        'postal_code',
        'cross_street',
        'directions',
        'zoning',
        'province',
        'latitude',
        'longitude',
        'days_on_market',
        'bedrooms',
        'bedrooms_possible',
        'bedrooms_above_grade',
        'bedrooms_below_grade',
        'bathrooms',
        'rooms_total',
        'kitchens_total',
        'washrooms',
        'square_feet',
        'square_feet_text',
        'lot_size_area',
        'lot_size_units',
        'lot_depth',
        'lot_width',
        'stories',
        'approximate_age',
        'structure_type',
        'basement',
        'basement_yn',
        'foundation_details',
        'construction_materials',
        'roof',
        'architectural_style',
        'heating_type',
        'heating_source',
        'cooling',
        'fireplace_yn',
        'fireplace_features',
        'fireplaces_total',
        'garage_type',
        'garage_yn',
        'garage_parking_spaces',
        'parking_total',
        'parking_features',
        'pool_features',
        'exterior_features',
        'interior_features',
        'water',
        'sewer',
        'tax_annual_amount',
        'tax_year',
        'association_fee',
        'list_price',
        'original_list_price',
        'listed_at',
        'price',
        'price_low',
        'price_per_square_foot',
        'price_change',
        'price_change_direction',
        'is_address_public',
        'parcel_id',
        'list_office_name',
        'list_office_phone',
        'list_aor',
        'virtual_tour_url',
        'modified_at',
        'payload',
        'ingestion_batch_id',
        'suppressed_at',
        'suppression_expires_at',
        'suppressed_by_user_id',
        'suppression_reason',
        'suppression_notes',
    ];

    protected function casts(): array
    {
        return [
            'association_fee' => 'decimal:2',
            'bathrooms' => 'decimal:1',
            'is_address_public' => 'boolean',
            'latitude' => 'decimal:7',
            'list_price' => 'decimal:2',
            'listed_at' => 'datetime',
            'longitude' => 'decimal:7',
            'lot_depth' => 'decimal:2',
            'lot_size_area' => 'decimal:2',
            'lot_width' => 'decimal:2',
            'modified_at' => 'datetime',
            'original_list_price' => 'decimal:2',
            'payload' => 'array',
            'price' => 'decimal:2',
            'price_low' => 'decimal:2',
            'price_per_square_foot' => 'decimal:2',
            'public_remarks' => 'string',
            'stories' => 'integer',
            'suppressed_at' => 'datetime',
            'suppression_expires_at' => 'datetime',
            'tax_annual_amount' => 'decimal:2',
            'transaction_type' => 'string',
            // New JSON casts
            'basement' => 'array',
            'basement_yn' => 'boolean',
            'foundation_details' => 'array',
            'construction_materials' => 'array',
            'roof' => 'array',
            'architectural_style' => 'array',
            'cooling' => 'array',
            'fireplace_yn' => 'boolean',
            'fireplace_features' => 'array',
            'garage_yn' => 'boolean',
            'parking_features' => 'array',
            'pool_features' => 'array',
            'exterior_features' => 'array',
            'interior_features' => 'array',
            'sewer' => 'array',
            'washrooms' => 'array',
        ];
    }

    public function getDaysOnMarketAttribute($value): ?int
    {
        if ($this->listed_at === null) {
            return $value !== null ? (int) $value : null;
        }

        $listedAt = $this->listed_at instanceof Carbon
            ? $this->listed_at
            : Carbon::parse((string) $this->listed_at);

        $days = $listedAt->diffInDays(Carbon::now(), false);

        if ($days < 0) {
            return 0;
        }

        return $days;
    }

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
     * @return HasMany<ListingSuppression>
     */
    public function suppressions(): HasMany
    {
        return $this->hasMany(ListingSuppression::class);
    }

    /**
     * @return HasOne<ListingSuppression>
     */
    public function currentSuppression(): HasOne
    {
        return $this->hasOne(ListingSuppression::class)->whereNull('released_at')->latestOfMany('suppressed_at');
    }

    /**
     * @return BelongsTo<User, Listing>
     */
    public function suppressedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suppressed_by_user_id');
    }

    /**
     * Scope to listings that are not currently suppressed.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisible(Builder $query): Builder
    {
        if (! self::suppressionSchemaAvailable()) {
            return $query;
        }

        $now = now();

        return $query->where(function (Builder $builder) use ($now): void {
            $builder
                ->whereNull('suppressed_at')
                ->orWhere(function (Builder $inner) use ($now): void {
                    $inner
                        ->whereNotNull('suppressed_at')
                        ->whereNotNull('suppression_expires_at')
                        ->where('suppression_expires_at', '<=', $now);
                });
        });
    }

    /**
     * Scope to listings that are currently suppressed.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSuppressed(Builder $query): Builder
    {
        if (! self::suppressionSchemaAvailable()) {
            return $query->whereRaw('1 = 0');
        }

        $now = now();

        return $query->whereNotNull('suppressed_at')->where(function (Builder $builder) use ($now): void {
            $builder
                ->whereNull('suppression_expires_at')
                ->orWhere('suppression_expires_at', '>', $now);
        });
    }

    public function isSuppressed(): bool
    {
        if (! self::suppressionSchemaAvailable()) {
            return false;
        }

        if ($this->suppressed_at === null) {
            return false;
        }

        if ($this->suppression_expires_at === null) {
            return true;
        }

        return $this->suppression_expires_at->isFuture();
    }

    public static function suppressionSchemaAvailable(): bool
    {
        static $available;

        if ($available === null) {
            try {
                $available = Schema::hasColumn((new self)->getTable(), 'suppressed_at')
                    && Schema::hasTable((new ListingSuppression)->getTable());
            } catch (\Throwable) {
                $available = false;
            }
        }

        return $available;
    }

    // Payload handling helpers moved to InteractsWithListingPayload trait.
}
