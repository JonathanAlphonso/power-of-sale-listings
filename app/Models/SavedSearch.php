<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SavedSearch extends Model
{
    /** @use HasFactory<\Database\Factories\SavedSearchFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'notification_channel',
        'notification_frequency',
        'is_active',
        'last_ran_at',
        'last_matched_at',
        'next_run_at',
        'filters',
        'meta',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'filters' => 'array',
        'is_active' => 'boolean',
        'last_matched_at' => 'datetime',
        'last_ran_at' => 'datetime',
        'meta' => 'array',
        'next_run_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SavedSearch $search): void {
            if (! $search->slug) {
                $search->slug = Str::slug(sprintf(
                    '%s-%s',
                    $search->user_id,
                    Str::limit($search->name, 32, ''),
                ));
            }
        });
    }

    /**
     * @return BelongsTo<User, SavedSearch>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
