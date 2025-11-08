<?php

use App\Models\Listing;
use App\Jobs\ImportIdxPowerOfSale;
use App\Jobs\ImportVowPowerOfSale;
use App\Jobs\ImportAllPowerOfSaleFeeds;
use App\Services\Idx\IdxClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public bool $tested = false;
    public bool $connected = false;
    #[Url]
    public string $notice = '';
    /** @var array<int, array<string, mixed>> */
    public array $preview = [];
    public ?int $requestMs = null;
    public int $previewImageCount = 0;

    /** @var array{configured: bool, base: string, tokenSet: bool, status: int|null, items: int|null, size: int|null, firstKeys: array<int,string>, error: string|null, checkedAt: string|null, fallback?: bool} */
    public array $idxCheck = [
        'configured' => false,
        'base' => '',
        'tokenSet' => false,
        'status' => null,
        'items' => null,
        'size' => null,
        'firstKeys' => [],
        'error' => null,
        'checkedAt' => null,
        'fallback' => false,
    ];

    /** @var array{configured: bool, base: string, tokenSet: bool, status: int|null, items: int|null, size: int|null, firstKeys: array<int,string>, error: string|null, checkedAt: string|null, fallback?: bool} */
    public array $vowCheck = [
        'configured' => false,
        'base' => '',
        'tokenSet' => false,
        'status' => null,
        'items' => null,
        'size' => null,
        'firstKeys' => [],
        'error' => null,
        'checkedAt' => null,
        'fallback' => false,
    ];

    public function mount(IdxClient $idx): void
    {
        Gate::authorize('access-admin-area');

        // Do not auto-fetch; only compute simple readiness state.
        $this->connected = $idx->isEnabled();

        // Prefer flashed session notice; fall back to existing bound query value
        // This ensures tests using `?notice=...` show the notice even without a flash
        $this->notice = $this->notice !== ''
            ? $this->notice
            : (string) session('notice', '');

        // Initialize quick-check summaries
        $this->idxCheck = $this->buildConfigCheck('idx');
        $this->vowCheck = $this->buildConfigCheck('vow');
    }

    public function testConnection(IdxClient $idx): void
    {
        $this->tested = true;

        try {
            // Use a tiny preview to reduce latency.
            $start = microtime(true);
            $this->preview = $idx->fetchPowerOfSaleListings(4);
            if ($this->preview === [] && (bool) config('services.idx.homepage_fallback_to_active', true)) {
                // Fallback to StandardStatus=Active when there are no current PoS listings
                $this->preview = $idx->fetchListings(4);
            }
            $this->requestMs = (int) round((microtime(true) - $start) * 1000);
            $this->previewImageCount = collect($this->preview)
                ->filter(fn ($i) => is_array($i) && ! empty($i['image_url'] ?? null))
                ->count();
            // Connection readiness is based on credentials, not result count
            $this->connected = $idx->isEnabled();
            $this->notice = $this->preview !== []
                ? __('IDX connection successful. Preview updated.')
                : __('IDX connection responded with no results.');
        } catch (\Throwable $e) {
            $this->connected = false;
            $this->notice = __('IDX request failed: :msg', ['msg' => $e->getMessage()]);
        }
    }

    public function refreshPreview(IdxClient $idx): void
    {
        // Clear the homepage demo cache, then re-fetch.
        Cache::forget('idx.pos.listings.4');
        $this->testConnection($idx);
    }

    public function importAllPowerOfSale(): void
    {
        // Queue the import to avoid request timeouts.
        ImportIdxPowerOfSale::dispatch(50, 200);
        $this->notice = __('IDX import queued');
        session()->flash('notice', $this->notice);
        $this->redirect(route('admin.feeds.index'));
    }

    public function importVow(): void
    {
        ImportVowPowerOfSale::dispatch(50, 200);
        $this->notice = __('VOW import queued');
        session()->flash('notice', $this->notice);
        $this->redirect(route('admin.feeds.index'));
    }

    public function importBoth(): void
    {
        // Skip if already running
        $progress = (array) Cache::get('idx.import.pos', []);
        if (($progress['status'] ?? null) === 'running') {
            $this->notice = __('Import already running');
            session()->flash('notice', $this->notice);
            $this->redirect(route('admin.feeds.index'));
            return;
        }

        // Skip if already queued in database driver
        $alreadyQueued = false;
        try {
            if ((string) config('queue.default') === 'database') {
                $table = (string) config('queue.connections.database.table', 'jobs');
                $alreadyQueued = DB::table($table)
                    ->where(function ($q) {
                        $q->where('payload', 'like', '%ImportAllPowerOfSaleFeeds%')
                          ->orWhere('payload', 'like', '%ImportIdxPowerOfSale%')
                          ->orWhere('payload', 'like', '%ImportVowPowerOfSale%');
                    })
                    ->exists();
            }
        } catch (\Throwable) {
            // ignore
        }

        if ($alreadyQueued) {
            $this->notice = __('Import already queued');
            session()->flash('notice', $this->notice);
            $this->redirect(route('admin.feeds.index'));
            return;
        }

        ImportAllPowerOfSaleFeeds::dispatch(50, 200);
        $this->notice = __('Import queued');
        session()->flash('notice', $this->notice);
        $this->redirect(route('admin.feeds.index'));
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
        return Cache::remember('admin.feeds.db_stats', now()->addSeconds(60), function (): array {
            return [
                'total' => Listing::query()->count(),
                'available' => Listing::query()->where('display_status', 'Available')->count(),
                'latest' => optional(Listing::query()->latest('modified_at')->value('modified_at')),
            ];
        });
    }

    #[Computed]
    public function statusCounts(): array
    {
        return Cache::remember('admin.feeds.status_counts', now()->addSeconds(60), function (): array {
            return Listing::query()
                ->select('display_status', DB::raw('COUNT(*) as total'))
                ->whereNotNull('display_status')
                ->groupBy('display_status')
                ->orderByDesc('total')
                ->limit(6)
                ->pluck('total', 'display_status')
                ->toArray();
        });
    }

    #[Computed]
    public function suppressionCount(): int
    {
        return Cache::remember('admin.feeds.suppression_count', now()->addSeconds(60), function (): int {
            return Listing::query()->suppressed()->count();
        });
    }

    #[Computed]
    public function priceStats(): array
    {
        return Cache::remember('admin.feeds.price_stats', now()->addSeconds(60), function (): array {
            return [
                'avg' => (float) (Listing::query()->avg('list_price') ?? 0),
                'min' => (float) (Listing::query()->min('list_price') ?? 0),
                'max' => (float) (Listing::query()->max('list_price') ?? 0),
            ];
        });
    }

    public function testIdxRequest(): void
    {
        $this->idxCheck = $this->runProbe('idx');
    }

    public function testVowRequest(): void
    {
        $this->vowCheck = $this->runProbe('vow');
    }

    #[Computed]
    public function topMunicipalities(): array
    {
        return Cache::remember('admin.feeds.top_municipalities', now()->addSeconds(60), function (): array {
            return DB::table('municipalities')
                ->join('listings', 'listings.municipality_id', '=', 'municipalities.id')
                ->select('municipalities.name', DB::raw('COUNT(listings.id) as total'))
                ->groupBy('municipalities.id', 'municipalities.name')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(fn ($r) => ['name' => $r->name, 'total' => (int) $r->total])
                ->all();
        });
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
        $this->notice = __('Homepage feed cache cleared.');
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
        $this->notice = __('HTTP metrics cleared.');
    }

    #[Computed]
    public function importStatus(): array
    {
        $status = (array) Cache::get('idx.import.pos', []);
        $state = (string) ($status['status'] ?? '');
        $running = $state === 'running';
        $items = (int) ($status['items_total'] ?? 0);
        $pages = (int) ($status['pages'] ?? 0);
        $startedAt = (string) ($status['started_at'] ?? '');
        $lastAt = (string) ($status['last_at'] ?? '');
        $finishedAt = (string) ($status['finished_at'] ?? '');

        // Best-effort: detect queued jobs waiting to run (database driver)
        $pending = false;
        $queueDetails = [
            'driver' => (string) config('queue.default'),
            'table' => null,
            'match_count' => null,
            'total_count' => null,
            'oldest_created_at' => null,
            'next_available_at' => null,
            'jobs' => [], // up to 5
        ];
        try {
            $driver = (string) config('queue.default');
            $queueDetails['driver'] = $driver;
            if ($driver === 'database') {
                $table = (string) config('queue.connections.database.table', 'jobs');
                $queueDetails['table'] = $table;

                $matching = DB::table($table)
                    ->select('id','queue','attempts','reserved_at','available_at','created_at','payload')
                    ->where(function ($q) {
                        $q->where('payload', 'like', '%ImportAllPowerOfSaleFeeds%')
                          ->orWhere('payload', 'like', '%ImportIdxPowerOfSale%')
                          ->orWhere('payload', 'like', '%ImportVowPowerOfSale%');
                    })
                    ->orderBy('id')
                    ->get();

                $queueDetails['match_count'] = $matching->count();
                $pending = $matching->count() > 0;

                $total = DB::table($table)->count();
                $queueDetails['total_count'] = $total;

                $first = $matching->first();
                $last = $matching->last();

                $toIso = function ($ts) {
                    return is_null($ts) ? null : CarbonImmutable::createFromTimestamp((int) $ts)->toIso8601String();
                };

                if ($first) {
                    $queueDetails['oldest_created_at'] = $toIso($first->created_at ?? null);
                    $queueDetails['next_available_at'] = $toIso($first->available_at ?? null);
                }

                $queueDetails['jobs'] = collect($matching)->take(5)->map(function ($j) use ($toIso) {
                    $type = 'unknown';
                    $payload = (string) ($j->payload ?? '');
                    if (str_contains($payload, 'ImportAllPowerOfSaleFeeds')) { $type = 'ImportAllPowerOfSaleFeeds'; }
                    elseif (str_contains($payload, 'ImportIdxPowerOfSale')) { $type = 'ImportIdxPowerOfSale'; }
                    elseif (str_contains($payload, 'ImportVowPowerOfSale')) { $type = 'ImportVowPowerOfSale'; }

                    return [
                        'id' => (int) $j->id,
                        'queue' => (string) $j->queue,
                        'attempts' => (int) $j->attempts,
                        'reserved_at' => $toIso($j->reserved_at ?? null),
                        'available_at' => $toIso($j->available_at ?? null),
                        'created_at' => $toIso($j->created_at ?? null),
                        'type' => $type,
                    ];
                })->all();
            }
        } catch (\Throwable) {
            // Ignore if queue driver is not database or table missing
        }

        return [
            'running' => $running,
            'pending' => $pending,
            'status' => $state !== '' ? $state : ($pending ? 'queued' : 'idle'),
            'items' => $items,
            'pages' => $pages,
            'started_at' => $startedAt,
            'last_at' => $lastAt,
            'finished_at' => $finishedAt,
            'queue' => $queueDetails,
        ];
    }

    public function refreshQueueInfo(): void
    {
        // No-op: triggers a re-render so computed queue info refreshes.
    }

    public function cancelQueuedImports(): void
    {
        $deleted = 0;
        try {
            if ((string) config('queue.default') === 'database') {
                $table = (string) config('queue.connections.database.table', 'jobs');
                $deleted = DB::table($table)
                    ->whereNull('reserved_at')
                    ->where(function ($q) {
                        $q->where('payload', 'like', '%ImportAllPowerOfSaleFeeds%')
                          ->orWhere('payload', 'like', '%ImportIdxPowerOfSale%')
                          ->orWhere('payload', 'like', '%ImportVowPowerOfSale%');
                    })
                    ->delete();
            }
        } catch (\Throwable) {
            $deleted = 0;
        }

        $this->notice = trans_choice(':n queued import job cancelled.|:n queued import jobs cancelled.', $deleted, ['n' => $deleted]);
        session()->flash('notice', $this->notice);
        $this->redirect(route('admin.feeds.index'));
    }

    private function buildConfigCheck(string $name): array
    {
        $base = (string) config("services.{$name}.base_uri", '');
        $idxBase = (string) config('services.idx.base_uri', '');
        $token = (string) config("services.{$name}.token", '');

        // Allow VOW tests to fall back to IDX base if VOW_BASE_URI is missing but token is present
        $usingFallback = $name === 'vow' && $base === '' && filled($idxBase);
        $effectiveBase = $usingFallback ? $idxBase : $base;

        return [
            'configured' => filled($effectiveBase) && filled($token),
            'base' => $effectiveBase,
            'tokenSet' => filled($token),
            'status' => null,
            'items' => null,
            'size' => null,
            'firstKeys' => [],
            'error' => null,
            'checkedAt' => null,
            'fallback' => $usingFallback,
        ];
    }

    private function runProbe(string $name): array
    {
        $check = $this->buildConfigCheck($name);

        if (! $check['configured']) {
            $check['error'] = 'Missing base URI or token';
            return $check;
        }

        $base = rtrim((string) $check['base'], '/');
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
            $resp = \Http::retry(2, 250)
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
}; ?>

<section class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-2 pb-6">
        <flux:heading size="xl">{{ __('Data Feeds') }}</flux:heading>
        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Manage external data feeds, test connectivity, and view a live preview of incoming records.') }}
        </flux:text>
        @php($__uiNotice = $notice !== '' ? $notice : (string) session('notice', ''))
        @if (! empty($__uiNotice))
            <flux:callout
                variant="success"
                icon="check-circle"
                x-data="{ visible: true }"
                x-show="visible"
                x-init="setTimeout(() => visible = false, 10000)"
                data-min-visible-ms="10000"
                data-ui-notice="{{ $__uiNotice }}"
                class="ring-2 ring-emerald-500/30"
                role="status"
            >
                {{ $__uiNotice }}
            </flux:callout>
        @endif

        @php($__importTop = $this->importStatus)
        @if ($__importTop['running'] || $__importTop['pending'])
            <flux:callout
                variant="neutral"
                icon="information-circle"
                role="status"
            >
                <span class="font-semibold">{{ __('Import status:') }}</span>
                @if ($__importTop['running'])
                    <span class="text-emerald-600 dark:text-emerald-400">{{ __('Running') }}</span>
                    <span class="text-zinc-500">—</span>
                    <span>{{ number_format($__importTop['items']) }} {{ __('items') }}, {{ number_format($__importTop['pages']) }} {{ __('pages') }}</span>
                @else
                    <span class="text-amber-600 dark:text-amber-400">{{ __('Queued') }}</span>
                @endif
            </flux:callout>
        @endif
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

                <flux:button variant="outline" wire:click="testIdxRequest" wire:loading.attr="disabled" wire:target="testIdxRequest">
                    <span wire:loading.remove wire:target="testIdxRequest">{{ __('Test IDX (30)') }}</span>
                    <span wire:loading wire:target="testIdxRequest" class="inline-flex items-center gap-2">
                        <flux:icon name="arrow-path" class="animate-spin" />
                        {{ __('Testing…') }}
                    </span>
                </flux:button>

                @if($notice !== '')
                    <span class="text-xs {{ $connected ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300' }}">{{ $notice }}</span>
                @endif
                @if($requestMs)
                    <span class="text-xs text-zinc-600 dark:text-zinc-400">{{ __('Last test: :ms ms', ['ms' => $requestMs]) }}</span>
                @endif
            </div>

            <div class="mt-3 grid gap-2 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('IDX last check') }}</dt>
                    <dd class="font-mono">{{ $idxCheck['checkedAt'] ?: '—' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('HTTP') }}</dt>
                    <dd class="font-semibold">{{ $idxCheck['status'] ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Items') }}</dt>
                    <dd class="font-semibold">{{ $idxCheck['items'] ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Size') }}</dt>
                    <dd class="font-semibold">{{ $idxCheck['size'] ? number_format($idxCheck['size']) . ' bytes' : '—' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('First keys') }}</dt>
                    <dd class="font-mono truncate">{{ implode(', ', $idxCheck['firstKeys'] ?: []) ?: '—' }}</dd>
                </div>
                @if ($idxCheck['error'])
                    <div class="text-xs text-red-600 dark:text-red-400">{{ $idxCheck['error'] }}</div>
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
            <flux:heading size="sm" class="mb-3">{{ __('VOW / PropTx Status') }}</flux:heading>

            <dl class="grid gap-3 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Base URL') }}</dt>
                    <dd class="font-mono text-zinc-900 dark:text-zinc-100">{{ $vowCheck['base'] !== '' ? $vowCheck['base'] : '—' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Token configured') }}</dt>
                    <dd class="font-semibold {{ $vowCheck['tokenSet'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $vowCheck['tokenSet'] ? __('Yes') : __('No') }}
                    </dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Last check') }}</dt>
                    <dd class="font-mono">{{ $vowCheck['checkedAt'] ?: '—' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('HTTP') }}</dt>
                    <dd class="font-semibold">{{ $vowCheck['status'] ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Items') }}</dt>
                    <dd class="font-semibold">{{ $vowCheck['items'] ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Size') }}</dt>
                    <dd class="font-semibold">{{ $vowCheck['size'] ? number_format($vowCheck['size']) . ' bytes' : '—' }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('First keys') }}</dt>
                    <dd class="font-mono truncate">{{ implode(', ', $vowCheck['firstKeys'] ?: []) ?: '—' }}</dd>
                </div>
                @if ($vowCheck['error'])
                    <div class="text-xs text-red-600 dark:text-red-400">{{ $vowCheck['error'] }}</div>
                @endif
                @if ($vowCheck['fallback'])
                    <div class="text-xs text-amber-700 dark:text-amber-300">{{ __('Using IDX_BASE_URI as fallback base URL') }}</div>
                @endif
            </dl>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <flux:button variant="primary" wire:click="testVowRequest" wire:loading.attr="disabled" wire:target="testVowRequest">
                    <span wire:loading.remove wire:target="testVowRequest">{{ __('Test VOW (30)') }}</span>
                    <span wire:loading wire:target="testVowRequest" class="inline-flex items-center gap-2">
                        <flux:icon name="arrow-path" class="animate-spin" />
                        {{ __('Testing…') }}
                    </span>
                </flux:button>
                <flux:button variant="outline" wire:click="importVow" wire:loading.attr="disabled" wire:target="importVow">
                    <span wire:loading.remove wire:target="importVow">{{ __('Import VOW') }}</span>
                    <span wire:loading wire:target="importVow" class="inline-flex items-center gap-2">
                        <flux:icon name="arrow-path" class="animate-spin" />
                        {{ __('Importing…') }}
                    </span>
                </flux:button>
                <flux:button icon="arrows-right-left" wire:click="importBoth" wire:loading.attr="disabled" wire:target="importBoth">
                    <span wire:loading.remove wire:target="importBoth">{{ __('Import Both Now') }}</span>
                    <span wire:loading wire:target="importBoth" class="inline-flex items-center gap-2">
                        <flux:icon name="arrow-path" class="animate-spin" />
                        {{ __('Importing…') }}
                    </span>
                </flux:button>
                <flux:button variant="outline" icon="x-mark" wire:click="cancelQueuedImports" wire:loading.attr="disabled" wire:target="cancelQueuedImports">
                    <span wire:loading.remove wire:target="cancelQueuedImports">{{ __('Cancel queued imports') }}</span>
                    <span wire:loading wire:target="cancelQueuedImports" class="inline-flex items-center gap-2">
                        <flux:icon name="arrow-path" class="animate-spin" />
                        {{ __('Cancelling…') }}
                    </span>
                </flux:button>

                @php($__import = $this->importStatus)
                <div class="basis-full"></div>
                <flux:callout
                    variant="neutral"
                    icon="information-circle"
                    class="min-w-[16rem]"
                    role="status"
                >
                    <div class="flex items-center gap-2">
                        <span class="font-semibold">{{ __('Import status:') }}</span>
                        @if ($__import['running'])
                            <span class="text-emerald-600 dark:text-emerald-400">{{ __('Running') }}</span>
                            <span class="text-zinc-500">—</span>
                            <span>{{ number_format($__import['items']) }} {{ __('items') }}, {{ number_format($__import['pages']) }} {{ __('pages') }}</span>
                        @elseif ($__import['pending'])
                            <span class="text-amber-600 dark:text-amber-400">{{ __('Queued') }}</span>
                        @else
                            <span class="text-zinc-600 dark:text-zinc-400">{{ __('Idle') }}</span>
                        @endif
                    </div>
                    @php($__q = $__import['queue'])
                    <div class="mt-2 grid gap-1 text-xs text-zinc-600 dark:text-zinc-400">
                        <div>{{ __('Driver: :d', ['d' => $__q['driver'] ?? '—']) }}</div>
                        @if((string)($__q['driver'] ?? '') === 'database')
                            <div>{{ __('Table: :t', ['t' => $__q['table'] ?? '—']) }}</div>
                            <div>{{ __('Matching jobs: :m / Total: :t', ['m' => (int)($__q['match_count'] ?? 0), 't' => (int)($__q['total_count'] ?? 0)]) }}</div>
                            <div>
                                {{ __('Oldest created: :c', ['c' => $__q['oldest_created_at'] ?? '—']) }}
                                <span class="mx-1">•</span>
                                {{ __('Next available: :a', ['a' => $__q['next_available_at'] ?? '—']) }}
                            </div>
                            @if(!empty($__q['jobs']))
                                <ul class="mt-1 space-y-0.5">
                                    @foreach(array_slice($__q['jobs'], 0, 3) as $__job)
                                        <li>
                                            #{{ $__job['id'] }} — {{ $__job['type'] }} ({{ __('attempts') }}: {{ $__job['attempts'] }})
                                            <span class="mx-1">•</span>
                                            {{ __('created') }}: {{ $__job['created_at'] ?? '—' }}
                                            @if($__job['reserved_at'])
                                                <span class="mx-1">•</span>
                                                {{ __('reserved') }}: {{ $__job['reserved_at'] }}
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
                    </div>
                    <div class="mt-2">
                        <flux:button size="xs" variant="outline" icon="arrow-path" wire:click="refreshQueueInfo" wire:loading.attr="disabled" wire:target="refreshQueueInfo">
                            <span wire:loading.remove wire:target="refreshQueueInfo">{{ __('Refresh queue info') }}</span>
                            <span wire:loading wire:target="refreshQueueInfo" class="inline-flex items-center gap-1">
                                <flux:icon name="arrow-path" class="animate-spin" />
                                {{ __('Refreshing…') }}
                            </span>
                        </flux:button>
                    </div>
                </flux:callout>
                @php($__uiNoticeInline = $notice !== '' ? $notice : (string) session('notice', ''))
                @if (! empty($__uiNoticeInline))
                    <div class="basis-full">
                        <flux:callout
                            variant="neutral"
                            icon="information-circle"
                            x-data="{ visible: true }"
                            x-show="visible"
                            x-init="setTimeout(() => visible = false, 15000)"
                            data-min-visible-ms="15000"
                            data-ui-notice="{{ $__uiNoticeInline }}"
                        >
                            {{ $__uiNoticeInline }}
                        </flux:callout>
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
            <flux:heading size="sm" class="mb-3">{{ __('Homepage Feed Cache') }}</flux:heading>
            <dl class="grid gap-3 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Present') }}</dt>
                    <dd class="font-semibold">{{ $this->feedCache['present'] ? __('Yes') : __('No') }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-zinc-600 dark:text-zinc-400">{{ __('Cached items') }}</dt>
                    <dd class="font-semibold">{{ number_format($this->feedCache['count']) }}</dd>
                </div>
            </dl>
            <div class="mt-4 flex gap-2">
                <flux:button variant="outline" icon="trash" wire:click="clearFeedCache" wire:loading.attr="disabled">
                    {{ __('Clear feed cache') }}
                </flux:button>
            </div>
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
