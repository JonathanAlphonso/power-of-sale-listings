<?php

use App\Models\Listing;
use App\Jobs\ImportIdxPowerOfSale;
use App\Services\Idx\IdxClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public bool $tested = false;
    public bool $connected = false;
    public string $message = '';
    /** @var array<int, array<string, mixed>> */
    public array $preview = [];
    public ?int $requestMs = null;
    public int $previewImageCount = 0;

    public function mount(IdxClient $idx): void
    {
        Gate::authorize('access-admin-area');

        // Do not auto-fetch; only compute simple readiness state.
        $this->connected = $idx->isEnabled();
        $this->message = $this->connected
            ? __('IDX credentials detected.')
            : __('IDX credentials required. Add IDX_BASE_URI and IDX_TOKEN to .env.');
    }

    public function testConnection(IdxClient $idx): void
    {
        $this->tested = true;

        try {
            // Use a tiny preview to reduce latency.
            $start = microtime(true);
            $this->preview = $idx->fetchPowerOfSaleListings(4);
            $this->requestMs = (int) round((microtime(true) - $start) * 1000);
            $this->previewImageCount = collect($this->preview)
                ->filter(fn ($i) => is_array($i) && ! empty($i['image_url'] ?? null))
                ->count();
            $this->connected = $this->connected && $this->preview !== [];
            $this->message = $this->preview !== []
                ? __('IDX connection successful. Preview updated.')
                : __('IDX connection responded with no results.');
        } catch (\Throwable $e) {
            $this->connected = false;
            $this->message = __('IDX request failed: :msg', ['msg' => $e->getMessage()]);
        }
    }

    public function refreshPreview(IdxClient $idx): void
    {
        // Clear the homepage demo cache, then re-fetch.
        Cache::forget('idx.pos.listings.4');
        $this->testConnection($idx);
    }

    public function importAllPowerOfSale(IdxClient $idx): void
    {
        // Run the import synchronously so tests can assert on progress cache.
        // Page size kept modest to respect IDX APIs while ensuring progress is recorded.
        (new ImportIdxPowerOfSale(pageSize: 50, maxPages: 200))->handle($idx);

        $this->message = __('Import completed');
    }

    #[Computed]
    public function idxBaseUri(): string
    {
        return (string) config('services.idx.base_uri', '');
    }

    #[Computed]
    public function hasIdxToken(): bool
    {
        return filled(config('services.idx.token'));
    }

    #[Computed]
    public function dbStats(): array
    {
        return [
            'total' => Listing::query()->count(),
            'available' => Listing::query()->where('display_status', 'Available')->count(),
            'latest' => optional(Listing::query()->latest('modified_at')->value('modified_at')),
        ];
    }

    #[Computed]
    public function statusCounts(): array
    {
        return Listing::query()
            ->select('display_status', DB::raw('COUNT(*) as total'))
            ->whereNotNull('display_status')
            ->groupBy('display_status')
            ->orderByDesc('total')
            ->limit(6)
            ->pluck('total', 'display_status')
            ->toArray();
    }

    #[Computed]
    public function suppressionCount(): int
    {
        return Listing::query()->suppressed()->count();
    }

    #[Computed]
    public function priceStats(): array
    {
        return [
            'avg' => (float) (Listing::query()->avg('list_price') ?? 0),
            'min' => (float) (Listing::query()->min('list_price') ?? 0),
            'max' => (float) (Listing::query()->max('list_price') ?? 0),
        ];
    }

    #[Computed]
    public function topMunicipalities(): array
    {
        return DB::table('municipalities')
            ->join('listings', 'listings.municipality_id', '=', 'municipalities.id')
            ->select('municipalities.name', DB::raw('COUNT(listings.id) as total'))
            ->groupBy('municipalities.id', 'municipalities.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'total' => (int) $r->total])
            ->all();
    }

    #[Computed]
    public function feedCache(): array
    {
        $items = Cache::get('idx.pos.listings.4', []);

        return [
            'present' => Cache::has('idx.pos.listings.4'),
            'count' => is_array($items) ? count($items) : 0,
        ];
    }

    public function clearFeedCache(): void
    {
        Cache::forget('idx.pos.listings.4');
        $this->message = __('Homepage feed cache cleared.');
    }

    #[Computed]
    public function httpMetrics(): array
    {
        $get = fn (string $scope, string $key) => (int) (Cache::get("idx.metrics.{$scope}.{$key}") ?? 0);

        $property = [
            'total' => $get('property', 'total'),
            'success' => $get('property', 'success'),
            'r429' => $get('property', '429'),
            'r5xx' => $get('property', '5xx'),
            'other' => $get('property', 'other'),
        ];
        $media = [
            'total' => $get('media', 'total'),
            'success' => $get('media', 'success'),
            'r429' => $get('media', '429'),
            'r5xx' => $get('media', '5xx'),
            'other' => $get('media', 'other'),
        ];

        $rate = fn (array $m) => $m['total'] > 0 ? round(($m['success'] / max(1, $m['total'])) * 100, 1) : 0.0;

        return [
            'window_started' => Cache::get('idx.metrics.window_started'),
            'last_status' => Cache::get('idx.metrics.last_status'),
            'last_error' => Cache::get('idx.metrics.last_error'),
            'last_at' => Cache::get('idx.metrics.last_at'),
            'property' => $property + ['success_rate' => $rate($property)],
            'media' => $media + ['success_rate' => $rate($media)],
        ];
    }

    public function clearHttpMetrics(): void
    {
        foreach (['property', 'media'] as $scope) {
            foreach (['total','success','429','5xx','other'] as $key) {
                Cache::forget("idx.metrics.{$scope}.{$key}");
            }
        }
        foreach (['window_started','last_status','last_error','last_at'] as $key) {
            Cache::forget("idx.metrics.{$key}");
        }
        $this->message = __('HTTP metrics cleared.');
    }
}; ?>

<section class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-2 pb-6">
        <flux:heading size="xl">{{ __('Data Feeds') }}</flux:heading>
        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Manage external data feeds, test connectivity, and view a live preview of incoming records.') }}
        </flux:text>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
            <flux:heading size="sm" class="mb-3">{{ __('IDX / PropTx Status') }}</flux:heading>

            <dl class="grid gap-3 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Base URL') }}</dt>
                    <dd class="font-mono text-zinc-900 dark:text-zinc-100">{{ $this->idxBaseUri !== '' ? $this->idxBaseUri : '—' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Token configured') }}</dt>
                    <dd class="font-semibold {{ $this->hasIdxToken ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $this->hasIdxToken ? __('Yes') : __('No') }}
                    </dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Connectivity') }}</dt>
                    <dd class="font-semibold {{ $connected ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                        {{ $connected ? __('Ready') : __('Not configured') }}
                    </dd>
                </div>
            </dl>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <flux:button icon="arrow-path" wire:click="testConnection" wire:loading.attr="disabled" wire:target="testConnection">
                    <span wire:loading.remove wire:target="testConnection">{{ __('Test connection') }}</span>
                    <span wire:loading wire:target="testConnection" class="inline-flex items-center gap-2">
                        <flux:icon name="arrow-path" class="animate-spin" />
                        {{ __('Testing…') }}
                    </span>
                </flux:button>

                <flux:button variant="outline" icon="arrow-path" wire:click="refreshPreview" wire:loading.attr="disabled" wire:target="refreshPreview">
                    <span wire:loading.remove wire:target="refreshPreview">{{ __('Refresh preview') }}</span>
                    <span wire:loading wire:target="refreshPreview" class="inline-flex items-center gap-2">
                        <flux:icon name="arrow-path" class="animate-spin" />
                        {{ __('Refreshing…') }}
                    </span>
                </flux:button>

                @if($message !== '')
                    <span class="text-xs {{ $connected ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300' }}">{{ $message }}</span>
                @endif
                @if($requestMs)
                    <span class="text-xs text-zinc-600 dark:text-zinc-400">{{ __('Last test: :ms ms', ['ms' => $requestMs]) }}</span>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
            <flux:heading size="sm" class="mb-3">{{ __('Database Snapshot') }}</flux:heading>
            <dl class="grid gap-3 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Total listings') }}</dt>
                    <dd class="font-semibold">{{ number_format($this->dbStats['total']) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Available listings') }}</dt>
                    <dd class="font-semibold">{{ number_format($this->dbStats['available']) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Last modified at') }}</dt>
                    <dd class="font-mono">{{ optional($this->dbStats['latest'])?->timezone(config('app.timezone'))?->format('Y-m-d H:i') ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Suppressed listings') }}</dt>
                    <dd class="font-semibold">{{ number_format($this->suppressionCount) }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="mt-6 grid gap-6 md:grid-cols-2">
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
            <flux:heading size="sm" class="mb-3">{{ __('Feed Cache') }}</flux:heading>
            <dl class="grid gap-3 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Present') }}</dt>
                    <dd class="font-semibold {{ $this->feedCache['present'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-600 dark:text-zinc-400' }}">
                        {{ $this->feedCache['present'] ? __('Yes') : __('No') }}
                    </dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Items') }}</dt>
                    <dd class="font-semibold">{{ number_format($this->feedCache['count']) }}</dd>
                </div>
            </dl>
            <div class="mt-4 flex gap-2">
                <flux:button variant="outline" icon="trash" wire:click="clearFeedCache" wire:loading.attr="disabled">
                    {{ __('Clear cache') }}
                </flux:button>
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
            <flux:heading size="sm" class="mb-3">{{ __('Price Stats') }}</flux:heading>
            <dl class="grid gap-3 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Average list price') }}</dt>
                    <dd class="font-semibold">{{ \App\Support\ListingPresentation::currency($this->priceStats['avg']) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Min list price') }}</dt>
                    <dd class="font-semibold">{{ \App\Support\ListingPresentation::currency($this->priceStats['min']) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Max list price') }}</dt>
                    <dd class="font-semibold">{{ \App\Support\ListingPresentation::currency($this->priceStats['max']) }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
        <flux:heading size="sm" class="mb-3">{{ __('HTTP Metrics (24h)') }}</flux:heading>
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <flux:heading size="xs" class="mb-2 text-zinc-600 dark:text-zinc-400">{{ __('Property requests') }}</flux:heading>
                <dl class="grid gap-2 text-sm">
                    <div class="flex items-center justify-between"><dt>{{ __('Total') }}</dt><dd class="font-semibold">{{ number_format($this->httpMetrics['property']['total']) }}</dd></div>
                    <div class="flex items-center justify-between"><dt>{{ __('Success') }}</dt><dd class="font-semibold">{{ number_format($this->httpMetrics['property']['success']) }} ({{ $this->httpMetrics['property']['success_rate'] }}%)</dd></div>
                    <div class="flex items-center justify-between"><dt>{{ __('429') }}</dt><dd class="font-semibold">{{ number_format($this->httpMetrics['property']['r429']) }}</dd></div>
                    <div class="flex items-center justify-between"><dt>{{ __('5xx') }}</dt><dd class="font-semibold">{{ number_format($this->httpMetrics['property']['r5xx']) }}</dd></div>
                    <div class="flex items-center justify-between"><dt>{{ __('Other') }}</dt><dd class="font-semibold">{{ number_format($this->httpMetrics['property']['other']) }}</dd></div>
                </dl>
            </div>
            <div>
                <flux:heading size="xs" class="mb-2 text-zinc-600 dark:text-zinc-400">{{ __('Media requests') }}</flux:heading>
                <dl class="grid gap-2 text-sm">
                    <div class="flex items-center justify-between"><dt>{{ __('Total') }}</dt><dd class="font-semibold">{{ number_format($this->httpMetrics['media']['total']) }}</dd></div>
                    <div class="flex items-center justify-between"><dt>{{ __('Success') }}</dt><dd class="font-semibold">{{ number_format($this->httpMetrics['media']['success']) }} ({{ $this->httpMetrics['media']['success_rate'] }}%)</dd></div>
                    <div class="flex items-center justify-between"><dt>{{ __('429') }}</dt><dd class="font-semibold">{{ number_format($this->httpMetrics['media']['r429']) }}</dd></div>
                    <div class="flex items-center justify-between"><dt>{{ __('5xx') }}</dt><dd class="font-semibold">{{ number_format($this->httpMetrics['media']['r5xx']) }}</dd></div>
                    <div class="flex items-center justify-between"><dt>{{ __('Other') }}</dt><dd class="font-semibold">{{ number_format($this->httpMetrics['media']['other']) }}</dd></div>
                </dl>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-zinc-600 dark:text-zinc-400">
            @if($this->httpMetrics['last_status'])
                <span>{{ __('Last status: :s', ['s' => $this->httpMetrics['last_status']]) }}</span>
            @endif
            @if($this->httpMetrics['last_at'])
                <span>•</span>
                <span>{{ __('Last at: :t', ['t' => $this->httpMetrics['last_at']]) }}</span>
            @endif
            @if($this->httpMetrics['last_error'])
                <span class="text-amber-700 dark:text-amber-300">• {{ __('Last error: :e', ['e' => $this->httpMetrics['last_error']]) }}</span>
            @endif
        </div>
        <div class="mt-4 flex gap-2">
            <flux:button variant="outline" icon="trash" wire:click="clearHttpMetrics" wire:loading.attr="disabled">
                {{ __('Clear HTTP metrics') }}
            </flux:button>
        </div>
    </div>

    <div class="mt-6 grid gap-6 md:grid-cols-2">
        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
            <flux:heading size="sm" class="mb-3">{{ __('Status Breakdown') }}</flux:heading>
            @if(empty($this->statusCounts))
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No data available.') }}</flux:text>
            @else
                <ul class="space-y-2 text-sm">
                    @foreach($this->statusCounts as $label => $count)
                        <li class="flex items-center justify-between">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ $label }}</span>
                            <span class="font-semibold">{{ number_format($count) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
            <flux:heading size="sm" class="mb-3">{{ __('Top Municipalities') }}</flux:heading>
            @if(empty($this->topMunicipalities))
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('No data available.') }}</flux:text>
            @else
                <ul class="space-y-2 text-sm">
                    @foreach($this->topMunicipalities as $row)
                        <li class="flex items-center justify-between">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ $row['name'] }}</span>
                            <span class="font-semibold">{{ number_format($row['total']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
    <div class="mt-6 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
        <flux:heading size="sm" class="mb-3">{{ __('Live Preview (Power of Sale)') }}</flux:heading>

        @if (empty($preview))
            <flux:callout variant="neutral" icon="information-circle">
                {{ __('Run “Test connection” to load a small preview of Power of Sale listings from IDX.') }}
            </flux:callout>
        @else
            <div class="mb-3 flex items-center gap-3 text-xs text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Items: :n', ['n' => count($preview)]) }}</span>
                <span>•</span>
                <span>{{ __('With images: :n', ['n' => $previewImageCount]) }}</span>
                @if($requestMs)
                    <span>•</span>
                    <span>{{ __('Request time: :ms ms', ['ms' => $requestMs]) }}</span>
                @endif
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($preview as $item)
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Status') }}</span>
                            <flux:badge>{{ $item['status'] ?? '—' }}</flux:badge>
                        </div>
                        <div class="mt-2 text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $item['address'] ?? __('Address unavailable') }}
                        </div>
                        <div class="text-xs text-zinc-600 dark:text-zinc-400">
                            {{ $item['city'] ?? '' }} {{ $item['state'] ?? '' }} {{ $item['postal_code'] ?? '' }}
                        </div>
                        <div class="mt-2 text-sm text-emerald-700 dark:text-emerald-400">
                            {{ is_numeric($item['list_price'] ?? null) ? \App\Support\ListingPresentation::currency($item['list_price']) : '—' }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
