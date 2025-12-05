<?php

declare(strict_types=1);

use App\Services\Idx\IdxClient;

it('renders core homepage sections', function (): void {
    // Disable IDX to avoid any live calls and keep this focused on rendering.
    $mock = \Mockery::mock(IdxClient::class);
    $mock->shouldReceive('isEnabled')->once()->andReturn(false);
    $this->instance(IdxClient::class, $mock);

    $response = $this->get(route('home'));

    $response->assertOk();

    // Hero + primary sections
    $response->assertSeeText('Elevate Ontario power of sale discovery');
    $response->assertSeeText('Automation pipeline, end to end');
    $response->assertSeeText('Database diagnostics');
    $response->assertSeeText('Live IDX feed');
});

it('shows a helpful state when idx is enabled but no listings are returned', function (): void {
    // Disable fallback so we can assert the empty state reliably.
    config()->set('services.idx.homepage_fallback_to_active', false);
    $mock = \Mockery::mock(IdxClient::class);
    $mock->shouldReceive('isEnabled')->once()->andReturn(true);
    $mock->shouldReceive('fetchPowerOfSaleListings')->once()->with(4)->andReturn([]);
    $this->instance(IdxClient::class, $mock);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSeeText('Live IDX feed');
    $response->assertSeeText('No live listings returned');
    $response->assertSeeText('IDX connection is available, but no listings were returned');
});

it('recovers gracefully when idx fetch throws and still renders the page', function (): void {
    // Disable fallback so we can assert the empty state reliably when an exception occurs.
    config()->set('services.idx.homepage_fallback_to_active', false);
    $mock = \Mockery::mock(IdxClient::class);
    $mock->shouldReceive('isEnabled')->once()->andReturn(true);
    $mock->shouldReceive('fetchPowerOfSaleListings')->once()->with(4)->andThrow(new Exception('IDX failure'));
    $this->instance(IdxClient::class, $mock);

    $response = $this->get(route('home'));

    $response->assertOk();
    // When an exception occurs, we log and surface the empty state for listings.
    $response->assertSeeText('Live IDX feed');
    $response->assertSeeText('No live listings returned');
});

it('falls back to active listings when PoS results are empty and fallback is enabled', function (): void {
    config()->set('services.idx.homepage_fallback_to_active', true);

    $mock = \Mockery::mock(IdxClient::class);
    $mock->shouldReceive('isEnabled')->once()->andReturn(true);
    $mock->shouldReceive('fetchPowerOfSaleListings')->once()->with(4)->andReturn([]);
    $mock->shouldReceive('fetchListings')->once()->with(4)->andReturn([
        [
            'listing_key' => 'A1',
            'address' => '456 King St W, Toronto, ON M5V 1L7',
            'city' => 'Toronto',
            'state' => 'ON',
            'postal_code' => 'M5V 1L7',
            'list_price' => 599999,
            'status' => 'Active',
            'property_type' => 'Residential',
            'property_sub_type' => 'Condo Apt',
            'list_office_name' => 'Example Realty',
            'remarks' => 'Great downtown condo.',
            'modified_at' => \Carbon\CarbonImmutable::parse('2024-11-01 12:00:00'),
            'virtual_tour_url' => null,
        ],
    ]);
    $this->instance(IdxClient::class, $mock);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSeeText('Live IDX feed');
    $response->assertSeeText('456 King St W, Toronto, ON M5V 1L7');
    $response->assertSee('$599,999', false);
});

it('does not leak low-level database error details in non-local environments', function (): void {
    config()->set('app.env', 'production');

    $html = view('welcome.partials.database', [
        'dbConnected' => false,
        'databaseName' => 'example-db',
        'tableSample' => ['users', 'listings'],
        'userCount' => 10,
        'dbErrorMessage' => 'SQLSTATE[HY000] Access denied for user',
    ])->render();

    expect($html)->toContain('Database diagnostics')
        ->toContain('Unable to connect using the configured credentials.')
        ->not->toContain('Access denied for user')
        ->not->toContain('SHOW TABLES');
});

it('shows detailed database diagnostics, including errors, only in local environments', function (): void {
    config()->set('app.env', 'local');

    $html = view('welcome.partials.database', [
        'dbConnected' => false,
        'databaseName' => 'example-db',
        'tableSample' => ['users', 'listings'],
        'userCount' => 10,
        'dbErrorMessage' => 'SQLSTATE[HY000] Access denied for user',
    ])->render();

    expect($html)->toContain('Database diagnostics')
        ->toContain('Unable to connect using the configured credentials.')
        ->toContain('Access denied for user');
})
    ->group('local-only');
