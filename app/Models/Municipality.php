<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Municipality extends Model
{
    /** @use HasFactory<\Database\Factories\MunicipalityFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'slug',
        'name',
        'province',
        'region',
        'district',
        'latitude',
        'longitude',
        'meta',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Municipality $municipality): void {
            if (! $municipality->slug) {
                $municipality->slug = Str::slug(sprintf(
                    '%s-%s',
                    $municipality->province ?? 'on',
                    $municipality->name,
                ));
            }
        });
    }

    /**
     * @return HasMany<Listing>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }
}
