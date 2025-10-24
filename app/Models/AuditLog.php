<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    /** @use HasFactory<\Database\Factories\AuditLogFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'event_uuid',
        'action',
        'auditable_type',
        'auditable_id',
        'user_id',
        'old_values',
        'new_values',
        'meta',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'new_values' => 'array',
        'occurred_at' => 'datetime',
        'old_values' => 'array',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log): void {
            if (! $log->event_uuid) {
                $log->event_uuid = (string) Str::uuid();
            }

            if (! $log->occurred_at) {
                $log->occurred_at = now();
            }
        });
    }

    /**
     * @return MorphTo<Model, AuditLog>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, AuditLog>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
