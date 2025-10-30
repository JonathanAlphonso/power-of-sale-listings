<?php

use App\Models\Listing;
use Database\Seeders\DatabaseSeeder;

use function Pest\Laravel\seed;

test('database seeder creates at least 100 listings', function () {
    seed(DatabaseSeeder::class);

    expect(Listing::query()->count())->toBeGreaterThanOrEqual(100);
});
