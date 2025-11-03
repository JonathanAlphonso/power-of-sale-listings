<?php

use App\Models\AnalyticsSetting;
use App\Models\Listing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('guests are redirected from the dashboard', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('subscribers cannot access the dashboard when an admin exists', function (): void {
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->create();

    $this->actingAs($subscriber)
        ->get(route('dashboard'))
        ->assertForbidden();

    expect($admin->refresh()->isAdmin())->toBeTrue();
    expect($subscriber->refresh()->isAdmin())->toBeFalse();
});

test('admins can view the dashboard', function (): void {
    $admin = User::factory()->admin()->create();

    $availableListing = Listing::factory()->create([
        'display_status' => 'Available',
        'street_address' => '77 Market Street',
        'list_price' => 450000,
        'modified_at' => now(),
    ]);

    Listing::factory()
        ->create([
            'display_status' => 'Sold',
            'street_address' => '88 Bay Street',
            'modified_at' => now()->subDay(),
        ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Total listings')
        ->assertSee('Open listings workspace')
        ->assertSee('Available inventory')
        ->assertSee('Team members')
        ->assertSee('Manage users')
        ->assertSee('Analytics data unavailable')
        ->assertSee($availableListing->street_address)
        ->assertSee(route('admin.listings.index'), false);
});

test('dashboard displays analytics metrics when connected to google analytics', function (): void {
    Cache::flush();

    $admin = User::factory()->admin()->create();

    $credentials = AnalyticsSetting::factory()->configured()->raw()['service_account_credentials'];

    AnalyticsSetting::factory()
        ->primary()
        ->configured()
        ->create([
            'property_id' => '123456789',
            'service_account_credentials' => $credentials,
        ]);

    expect(AnalyticsSetting::current()->isConfigured())->toBeTrue();

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'test-token',
        ], 200),
        'https://analyticsdata.googleapis.com/*' => Http::response([
            'rows' => [
                [
                    'metricValues' => [
                        ['value' => '1234'],
                        ['value' => '567'],
                        ['value' => '890'],
                        ['value' => '0.45'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $testNow = CarbonImmutable::now()->startOfDay();
    CarbonImmutable::setTestNow($testNow);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Google Analytics')
        ->assertSee('Last 7 days')
        ->assertSee('1,234')
        ->assertSee('567')
        ->assertSee('890')
        ->assertSee('45.0%')
        ->assertDontSee('test-token'); // ensure token not leaked

    CarbonImmutable::setTestNow();
});

test('dashboard surfaces a helpful message when analytics data is unavailable', function (): void {
    Cache::flush();

    $admin = User::factory()->admin()->create();

    $credentials = AnalyticsSetting::factory()->configured()->raw()['service_account_credentials'];

    AnalyticsSetting::factory()
        ->primary()
        ->configured()
        ->create([
            'property_id' => '987654321',
            'service_account_credentials' => $credentials,
        ]);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'test-token',
        ], 200),
        'https://analyticsdata.googleapis.com/*' => Http::response([], 500),
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Analytics data unavailable')
        ->assertSee('Analytics data is temporarily unavailable.');
});

test('client tracking snippet is injected when enabled', function (): void {
    AnalyticsSetting::current()->forceFill([
        'client_enabled' => true,
        'measurement_id' => 'G-TRACK123',
    ])->save();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TRACK123', false)
        ->assertSee("gtag('config', 'G-TRACK123')", false);
});
