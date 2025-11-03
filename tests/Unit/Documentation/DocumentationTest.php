<?php

test('readme documents migration, seeding, and admin workspace commands', function (): void {
    $rootPath = dirname(__DIR__, 3);
    $contents = file_get_contents($rootPath.'/README.md');

    expect($contents)
        ->toContain('php artisan migrate --graceful')
        ->toContain('php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder')
        ->toContain('php artisan migrate:fresh --seed')
        ->toContain('/admin/listings')
        ->toContain('/admin/users')
        ->toContain('/admin/settings/analytics');
});

test('runbook highlights admin urls and database commands', function (): void {
    $rootPath = dirname(__DIR__, 3);
    $contents = file_get_contents($rootPath.'/Docs/runbook.md');

    expect($contents)
        ->toContain('php artisan migrate --graceful')
        ->toContain('php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder')
        ->toContain('/dashboard')
        ->toContain('/admin/listings')
        ->toContain('/admin/users')
        ->toContain('/admin/settings/analytics');
});
