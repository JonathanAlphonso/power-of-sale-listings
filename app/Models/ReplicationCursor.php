<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReplicationCursor extends Model
{
    /** @use HasFactory<\Database\Factories\ReplicationCursorFactory> */
    use HasFactory;

    protected $fillable = [
        'channel',
        'last_timestamp',
        'last_key',
    ];

    protected function casts(): array
    {
        return [
            'last_timestamp' => 'datetime',
        ];
    }
}
