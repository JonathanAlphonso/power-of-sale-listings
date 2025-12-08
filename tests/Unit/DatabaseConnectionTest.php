<?php

declare(strict_types=1);

use Tests\TestCase;

// Use TestCase but NOT RefreshDatabase - safe to run
uses(TestCase::class);

test('test environment uses sqlite in-memory database', function (): void {
    $connection = config('database.default');
    $database = config('database.connections.' . $connection . '.database');

    dump("Connection: {$connection}");
    dump("Database: {$database}");

    expect($connection)->toBe('sqlite', 'Tests MUST use sqlite connection, not ' . $connection);
    expect($database)->toBe(':memory:', 'Tests MUST use :memory: database, not ' . $database);
});
