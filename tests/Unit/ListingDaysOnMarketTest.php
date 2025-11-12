<?php

declare(strict_types=1);

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('derives days on market from the listed_at timestamp', function (): void {
    Carbon::setTestNow('2024-11-10 12:00:00');

    $listing = Listing::factory()->create([
        'listed_at' => Carbon::now()->subDays(12),
        'days_on_market' => 4,
    ]);

    expect($listing->fresh()->days_on_market)->toBe(12);

    Carbon::setTestNow();
});

it('falls back to the stored value when listed_at is missing', function (): void {
    $listing = Listing::factory()->create([
        'listed_at' => null,
        'days_on_market' => 9,
    ]);

    expect($listing->fresh()->days_on_market)->toBe(9);
});

it('never returns negative values when listed_at is in the future', function (): void {
    Carbon::setTestNow('2024-11-10 12:00:00');

    $listing = Listing::factory()->create([
        'listed_at' => Carbon::now()->addDay(),
        'days_on_market' => 7,
    ]);

    expect($listing->fresh()->days_on_market)->toBe(0);

    Carbon::setTestNow();
});
