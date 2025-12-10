<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ListingViewPivot extends Pivot
{
    protected $table = 'listing_views';

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }
}
