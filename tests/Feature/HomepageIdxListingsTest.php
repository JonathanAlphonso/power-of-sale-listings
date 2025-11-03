<?php

declare(strict_types=1);

use App\Services\Idx\IdxClient;
use Carbon\CarbonImmutable;

it('displays idx listings when available', function (): void {
    $mock = \Mockery::mock(IdxClient::class);
    $mock->shouldReceive('isEnabled')->once()->andReturn(true);
    $mock->shouldReceive('fetchListings')->once()->with(4)->andReturn([
        [
            'listing_key' => 'X123',
            'address' => '123 Main St, Toronto, ON M1M 1M1',
            'city' => 'Toronto',
            'state' => 'ON',
            'postal_code' => 'M1M 1M1',
            'list_price' => 750000,
            'status' => 'Active',
            'property_type' => 'Residential',
            'property_sub_type' => 'Detached',
            'list_office_name' => 'Demo Realty',
            'remarks' => 'Beautiful home near the lake.',
            'modified_at' => CarbonImmutable::parse('2024-10-10 12:00:00'),
            'virtual_tour_url' => 'https://example.test/tour',
        ],
    ]);

    $this->instance(IdxClient::class, $mock);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSeeText('Live IDX feed');
    $response->assertSeeText('123 Main St, Toronto, ON M1M 1M1');
    $response->assertSeeText('Active');
    $response->assertSee('$750,000', false);
});

it('shows guidance when idx credentials are missing', function (): void {
    $mock = \Mockery::mock(IdxClient::class);
    $mock->shouldReceive('isEnabled')->once()->andReturn(false);
    $mock->shouldReceive('fetchListings')->never();

    $this->instance(IdxClient::class, $mock);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSeeText('IDX credentials required');
    $response->assertSeeText('Add your IDX credentials to the environment to preview live listings from Amplify.');
});
