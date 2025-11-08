<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function acting_admin(): User
{
    return User::factory()->admin()->create();
}

test('test connection button loads preview and shows notice', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');

    // Fake PoS property and media
    Http::fake([
        'idx.example/odata/Property*' => Http::response([
            'value' => [[
                'ListingKey' => 'KBTN1',
                'ModificationTimestamp' => now()->toISOString(),
                'StandardStatus' => 'Active',
                'PublicRemarks' => 'Power of Sale',
                'ListPrice' => 111000,
            ]],
        ], 200),
        'idx.example/odata/Media*' => Http::response([
            'value' => [[
                'MediaURL' => 'https://img.example/foo.jpg',
                'MediaType' => 'image/jpeg',
                'ResourceName' => 'Property',
                'ResourceRecordKey' => 'KBTN1',
            ]],
        ], 200),
        '*' => Http::response(['value' => []], 200),
    ]);

    $admin = acting_admin();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    Volt::test('admin.feeds.index')
        ->call('testConnection')
        ->assertSee('IDX connection successful')
        ->assertSee('Items: 1')
        ->assertSee('With images: 1');
});

test('refresh preview clears cache and reloads preview', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');

    Cache::put('idx.pos.listings.4', [['listing_key' => 'OLD']], now()->addMinute());

    Http::fake([
        'idx.example/odata/Property*' => Http::response([
            'value' => [[
                'ListingKey' => 'KBTN2',
                'ModificationTimestamp' => now()->toISOString(),
                'StandardStatus' => 'Active',
                'PublicRemarks' => 'Power of Sale',
                'ListPrice' => 222000,
            ]],
        ], 200),
        'idx.example/odata/Media*' => Http::response(['value' => []], 200),
        '*' => Http::response(['value' => []], 200),
    ]);

    $admin = acting_admin();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    Volt::test('admin.feeds.index')
        ->call('refreshPreview')
        ->assertSee('Last test:');
});

test('idx and vow probe buttons populate status details', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');
    config()->set('services.vow.base_uri', 'https://idx.example/odata/');
    config()->set('services.vow.token', 'test-token');

    Http::fake([
        'idx.example/odata/Property*' => Http::response([
            'value' => [
                ['ListingKey' => 'KP1'],
                ['ListingKey' => 'KP2'],
            ],
        ], 200),
    ]);

    $admin = acting_admin();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    Volt::test('admin.feeds.index')
        ->call('testIdxRequest')
        ->call('testVowRequest')
        ->assertSee('HTTP')
        ->assertSee('Items')
        ->assertSee('KP1')
        ->assertSee('KP2');
});
