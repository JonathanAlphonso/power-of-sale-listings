<?php

use App\Jobs\ImportAllPowerOfSaleFeeds;
use App\Jobs\ImportIdxPowerOfSale;
use App\Jobs\ImportVowPowerOfSale;
use App\Services\Idx\IdxClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public ?string $flash = null;

    public array $idxCheck = [];
    public array $vowCheck = [];

    public function mount(): void
    {
        $this->idxCheck = $this->buildConfigCheck('idx');
        $this->vowCheck = $this->buildConfigCheck('vow');
    }

    public function testIdxRequest(): void
    {
        $this->idxCheck = $this->runProbe('idx');
    }

    public function testVowRequest(): void
    {
        $this->vowCheck = $this->runProbe('vow');
    }

    public function importIdx(IdxClient $idx): void
    {
        (new ImportIdxPowerOfSale(pageSize: 50, maxPages: 2))->handle($idx);
        $this->flash = 'IDX import completed.';
    }

    public function importVow(IdxClient $idx): void
    {
        (new ImportVowPowerOfSale(pageSize: 50, maxPages: 2))->handle($idx);
        $this->flash = 'VOW import completed.';
    }

    public function importBoth(IdxClient $idx): void
    {
        (new ImportAllPowerOfSaleFeeds(pageSize: 50, maxPages: 2))->handle($idx);
        $this->flash = 'Combined IDX + VOW import completed.';
    }

    private function buildConfigCheck(string $name): array
    {
        $base = (string) config("services.{$name}.base_uri", '');
        $token = (string) config("services.{$name}.token", '');

        return [
            'configured' => filled($base) && filled($token),
            'base' => $base,
            'tokenSet' => filled($token),
            'status' => null,
            'items' => null,
            'size' => null,
            'firstKeys' => [],
            'error' => null,
            'checkedAt' => null,
        ];
    }

    private function runProbe(string $name): array
    {
        $check = $this->buildConfigCheck($name);

        if (! $check['configured']) {
            $check['error'] = 'Missing base URI or token.';
            return $check;
        }

        $base = rtrim((string) config("services.{$name}.base_uri"), '/');
        $token = (string) config("services.{$name}.token");

        $filter = "PublicRemarks ne null and "
            . "startswith(TransactionType,'For Sale') and ("
            . "contains(PublicRemarks,'Power of Sale') or "
            . "contains(PublicRemarks,'power of sale') or "
            . "contains(PublicRemarks,'POWER OF SALE') or "
            . "contains(PublicRemarks,'Power Of Sale'))";

        $query = [
            '$top' => 30,
            '$filter' => $filter,
        ];

        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        try {
            $resp = Http::retry(2, 250)
                ->timeout(20)
                ->baseUrl($base)
                ->withToken($token)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'OData-Version' => '4.0',
                ])
                ->get('Property?'.$queryString);

            $json = $resp->json();
            $items = is_array($json) && is_array($json['value'] ?? null) ? (array) $json['value'] : [];

            $check['status'] = $resp->status();
            $check['items'] = count($items);
            $check['size'] = strlen($resp->body() ?? '');
            $check['firstKeys'] = array_values(array_filter(array_map(function ($row) {
                return is_array($row) && isset($row['ListingKey']) ? (string) $row['ListingKey'] : null;
            }, array_slice($items, 0, 5))));
            $check['checkedAt'] = now()->toIso8601String();
        } catch (\Throwable $e) {
            $check['error'] = $e->getMessage();
            $check['checkedAt'] = now()->toIso8601String();
        }

        return $check;
    }
};
?>

<section class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Data Feeds') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Run quick checks and imports for IDX and VOW.') }}
            </flux:text>
        </div>

        <div class="flex gap-2">
            <flux:button icon="arrows-right-left" wire:click="importBoth" :disabled="$wire.get('idxCheck.configured') === false && $wire.get('vowCheck.configured') === false">
                {{ __('Import Both Now') }}
            </flux:button>
        </div>
    </div>

    @if ($flash)
        <flux:callout title="{{ __('Done') }}" color="emerald" icon="check-circle" class="mb-4">
            {{ $flash }}
        </flux:callout>
    @endif

    <div class="grid gap-6 sm:grid-cols-2">
        <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-zinc-900/60">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">IDX Feed</h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $idxCheck['base'] ?: '—' }}</p>
                </div>
                <flux:badge :color="$idxCheck['configured'] ? 'emerald' : 'zinc'">{{ $idxCheck['configured'] ? 'Configured' : 'Missing' }}</flux:badge>
            </div>

            <div class="grid gap-2 text-sm">
                <div class="flex justify-between"><span class="text-zinc-600 dark:text-zinc-400">Token set</span><span class="font-medium">{{ $idxCheck['tokenSet'] ? 'Yes' : 'No' }}</span></div>
                <div class="flex justify-between"><span class="text-zinc-600 dark:text-zinc-400">Last check</span><span class="font-medium">{{ $idxCheck['checkedAt'] ?: '—' }}</span></div>
                <div class="flex justify-between"><span class="text-zinc-600 dark:text-zinc-400">HTTP</span><span class="font-medium">{{ $idxCheck['status'] ?: '—' }}</span></div>
                <div class="flex justify-between"><span class="text-zinc-600 dark:text-zinc-400">Items</span><span class="font-medium">{{ $idxCheck['items'] ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-zinc-600 dark:text-zinc-400">Size</span><span class="font-medium">{{ $idxCheck['size'] ? number_format($idxCheck['size']) .' bytes' : '—' }}</span></div>
                <div class="flex justify-between"><span class="text-zinc-600 dark:text-zinc-400">First keys</span><span class="font-medium truncate">{{ implode(', ', $idxCheck['firstKeys'] ?: []) ?: '—' }}</span></div>
                @if ($idxCheck['error'])
                    <div class="text-xs text-red-600 dark:text-red-400">{{ $idxCheck['error'] }}</div>
                @endif
            </div>

            <div class="mt-4 flex gap-2">
                <flux:button variant="primary" wire:click="testIdxRequest" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="testIdxRequest">{{ __('Test IDX (30)') }}</span>
                    <span wire:loading wire:target="testIdxRequest">{{ __('Testing…') }}</span>
                </flux:button>
                <flux:button wire:click="importIdx" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="importIdx">{{ __('Import IDX') }}</span>
                    <span wire:loading wire:target="importIdx">{{ __('Importing…') }}</span>
                </flux:button>
            </div>
        </div>

        <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-zinc-900/60">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">VOW Feed</h2>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $vowCheck['base'] ?: '—' }}</p>
                </div>
                <flux:badge :color="$vowCheck['configured'] ? 'emerald' : 'zinc'">{{ $vowCheck['configured'] ? 'Configured' : 'Missing' }}</flux:badge>
            </div>

            <div class="grid gap-2 text-sm">
                <div class="flex justify-between"><span class="text-zinc-600 dark:text-zinc-400">Token set</span><span class="font-medium">{{ $vowCheck['tokenSet'] ? 'Yes' : 'No' }}</span></div>
                <div class="flex justify-between"><span class="text-zinc-600 dark:text-zinc-400">Last check</span><span class="font-medium">{{ $vowCheck['checkedAt'] ?: '—' }}</span></div>
                <div class="flex justify-between"><span class="text-zinc-600 dark:text-zinc-400">HTTP</span><span class="font-medium">{{ $vowCheck['status'] ?: '—' }}</span></div>
                <div class="flex justify-between"><span class="text-zinc-600 dark:text-zinc-400">Items</span><span class="font-medium">{{ $vowCheck['items'] ?? '—' }}</span></div>
                <div class="flex justify-between"><span class="text-zinc-600 dark:text-zinc-400">Size</span><span class="font-medium">{{ $vowCheck['size'] ? number_format($vowCheck['size']) .' bytes' : '—' }}</span></div>
                <div class="flex justify-between"><span class="text-zinc-600 dark:text-zinc-400">First keys</span><span class="font-medium truncate">{{ implode(', ', $vowCheck['firstKeys'] ?: []) ?: '—' }}</span></div>
                @if ($vowCheck['error'])
                    <div class="text-xs text-red-600 dark:text-red-400">{{ $vowCheck['error'] }}</div>
                @endif
            </div>

            <div class="mt-4 flex gap-2">
                <flux:button variant="primary" wire:click="testVowRequest" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="testVowRequest">{{ __('Test VOW (30)') }}</span>
                    <span wire:loading wire:target="testVowRequest">{{ __('Testing…') }}</span>
                </flux:button>
                <flux:button wire:click="importVow" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="importVow">{{ __('Import VOW') }}</span>
                    <span wire:loading wire:target="importVow">{{ __('Importing…') }}</span>
                </flux:button>
            </div>
        </div>
    </div>
</section>
