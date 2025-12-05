<?php

use Illuminate\Support\Facades\DB;

test('debug: check database connection during test', function () {
    $connection = DB::connection();
    $name = $connection->getDatabaseName();
    $driver = $connection->getDriverName();

    dump("Driver: $driver");
    dump("Database: $name");
    dump("Default connection: " . config('database.default'));

    // Check user count in the test database
    $userCount = \App\Models\User::count();
    dump("User count in test DB: $userCount");

    // This should pass to see the output
    expect(true)->toBeTrue();
});
