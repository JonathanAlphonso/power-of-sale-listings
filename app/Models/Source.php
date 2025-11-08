<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Source extends Model
{
    /** @use HasFactory<\Database\Factories\SourceFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'slug',
        'name',
        'type',
        'external_identifier',
        'contact_name',
        'contact_email',
        'contact_phone',
        'website_url',
        'is_active',
        'last_synced_at',
        'config',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Source $source): void {
            if (! $source->slug) {
                $source->slug = Str::slug($source->name);
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
