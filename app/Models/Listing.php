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
        'listed_at',
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
        'suppressed_at',
        'suppression_expires_at',
        'suppressed_by_user_id',
        'suppression_reason',
        'suppression_notes',
    ];

    protected function casts(): array
    {
        return [
            'bathrooms' => 'decimal:1',
            'is_address_public' => 'boolean',
            'latitude' => 'decimal:7',
            'list_price' => 'decimal:2',
            'longitude' => 'decimal:7',
            'modified_at' => 'datetime',
            'original_list_price' => 'decimal:2',
            'payload' => 'array',
            'listed_at' => 'datetime',
            'price' => 'decimal:2',
            'price_low' => 'decimal:2',
            'price_per_square_foot' => 'decimal:2',
            'suppressed_at' => 'datetime',
            'suppression_expires_at' => 'datetime',
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
