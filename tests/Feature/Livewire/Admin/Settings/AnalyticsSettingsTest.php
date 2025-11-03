<?php

use App\Models\AnalyticsSetting;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    Volt::actingAs($this->admin);
});

it('renders the analytics settings page for administrators', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.analytics'))
        ->assertOk()
        ->assertSee('Google Analytics');
});

it('allows administrators to enable both client and server analytics', function (): void {
    $credentials = AnalyticsSetting::factory()->configured()->raw()['service_account_credentials'];

    $component = Volt::test('admin.settings.analytics')
        ->set('form.client_enabled', true)
        ->set('form.server_enabled', true)
        ->set('form.property_id', '123456789')
        ->set('form.measurement_id', 'g-demo1234')
        ->set('form.credentials', json_encode($credentials, JSON_PRETTY_PRINT));

    $component->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.server_enabled', true)
        ->assertSet('form.client_enabled', true);

    $setting = AnalyticsSetting::current();

    $storedCredentials = (array) $setting->service_account_credentials;

    expect($setting->enabled)->toBeTrue()
        ->and($setting->client_enabled)->toBeTrue()
        ->and($setting->property_id)->toBe('123456789')
        ->and($setting->measurement_id)->toBe('G-DEMO1234');

    expect($storedCredentials)->toBeArray();
    expect($storedCredentials['client_email'])->toBe($credentials['client_email']);
});

it('requires a property id when enabling server metrics', function (): void {
    $credentials = AnalyticsSetting::factory()->configured()->raw()['service_account_credentials'];

    $component = Volt::test('admin.settings.analytics')
        ->set('form.client_enabled', false)
        ->set('form.server_enabled', true)
        ->set('form.property_id', '')
        ->set('form.measurement_id', 'G-DEMO999')
        ->set('form.credentials', json_encode($credentials, JSON_PRETTY_PRINT));

    $component->call('save')
        ->assertHasErrors(['form.property_id']);

    $setting = AnalyticsSetting::current();

    expect($setting->enabled)->toBeFalse();
});

it('validates service account credentials structure', function (): void {
    $component = Volt::test('admin.settings.analytics')
        ->set('form.client_enabled', false)
        ->set('form.server_enabled', true)
        ->set('form.property_id', '987654321')
        ->set('form.measurement_id', 'G-DEMO888')
        ->set('form.credentials', json_encode(['client_email' => 'missing-fields@example.com']));

    $component->call('save')
        ->assertHasErrors(['form.credentials']);

    $setting = AnalyticsSetting::current();

    expect($setting->enabled)->toBeFalse();
});

it('requires a measurement id when any integration toggle is enabled', function (): void {
    Volt::test('admin.settings.analytics')
        ->set('form.client_enabled', true)
        ->set('form.server_enabled', false)
        ->set('form.measurement_id', '')
        ->call('save')
        ->assertHasErrors(['form.measurement_id']);
});

it('allows enabling client tracking without server credentials', function (): void {
    $component = Volt::test('admin.settings.analytics')
        ->set('form.client_enabled', true)
        ->set('form.server_enabled', false)
        ->set('form.measurement_id', 'G-LOCAL123');

    $component->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.client_enabled', true)
        ->assertSet('form.server_enabled', false);

    $setting = AnalyticsSetting::current();

    expect($setting->client_enabled)->toBeTrue()
        ->and($setting->enabled)->toBeFalse()
        ->and($setting->measurement_id)->toBe('G-LOCAL123');
});
