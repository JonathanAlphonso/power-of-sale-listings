<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Models\Municipality;
use App\Models\Source;
use App\Services\Idx\IdxClient;
use App\Services\Idx\ListingTransformer;
use App\Services\Idx\RequestFactory;
use App\Support\BoardCode;
use App\Support\ResoFilters;
use App\Support\ResoSelects;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class ImportVowPowerOfSale implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200; // 20 minutes

    public function __construct(public int $pageSize = 500, public int $maxPages = 200) {}

    /**
     * Prevent overlapping imports to avoid heavy duplicate work.
     *
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return [
            (new \Illuminate\Queue\Middleware\WithoutOverlapping('pos-import-vow'))->expireAfter($this->timeout),
        ];
    }

    public function handle(IdxClient $idx): void
    {
        /** @var ListingTransformer $transformer */
        $transformer = app(ListingTransformer::class);
        $top = max(1, min($this->pageSize, 100));
        $skip = 0;
        $pages = 0;
        $next = null;

        do {
            $pages++;
            $page = $this->fetchPowerOfSalePage($top, $skip, $next);
            $batch = $page['items'];
            $next = $page['next'];

            if ($batch === []) {
                break;
            }

            foreach ($batch as $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                $this->upsertListingFromRaw($idx, $transformer, $raw);
            }

            $skip += $top;
        } while ((count($batch) === $top || $next !== null) && $pages < $this->maxPages);
    }

    /**
     * Fetch a page of results. If nextUrl is provided, it is used directly (relative to base URI).
     *
     * @return array{items: array<int, array<string, mixed>>, next: ?string}
     */
    protected function fetchPowerOfSalePage(int $top, int $skip, ?string $nextUrl = null): array
    {
        // Base query components
        $select = ResoSelects::propertyPowerOfSaleImport();

        $filter = ResoFilters::powerOfSale();
        /** @var RequestFactory $factory */
        $factory = app(RequestFactory::class);
        $request = $factory->vowProperty(preferMaxPage: true);

        $response = $nextUrl !== null
            ? (function () use ($request, $nextUrl) {
                $next = (string) $nextUrl;

                $relative = $next;
                if (str_starts_with($next, 'http')) {
                    $path = (string) parse_url($next, PHP_URL_PATH);
                    $query = (string) parse_url($next, PHP_URL_QUERY);
                    $relative = ltrim($path, '/').($query !== '' ? ('?'.$query) : '');
                }

                return $request
                    ->baseUrl(rtrim((string) config('services.vow.base_uri', ''), '/'))
                    ->withHeaders(['Prefer' => 'odata.maxpagesize=500'])
                    ->get($relative);
            })()
            : $request
                ->get('Property', [
                    '$select' => $select,
                    '$filter' => $filter,
                    '$orderby' => 'ModificationTimestamp,ListingKey',
                    '$top' => $top,
                    '$skip' => $skip,
                ]);

        if ($response->failed()) {
            return ['items' => [], 'next' => null];
        }

        $items = $response->json('value');
        $root = $response->json();

        $items = is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
        $next = (is_array($root) && isset($root['@odata.nextLink']) && is_string($root['@odata.nextLink']) && $root['@odata.nextLink'] !== '')
            ? $root['@odata.nextLink']
            : null;

        return ['items' => $items, 'next' => $next];
    }

    protected function upsertListingFromRaw(IdxClient $idx, ListingTransformer $transformer, array $raw): void
    {
        $key = Arr::get($raw, 'ListingKey');
        if (! is_string($key) || $key === '') {
            return;
        }

        $city = Arr::get($raw, 'City');
        $province = Arr::get($raw, 'StateOrProvince') ?: 'ON';
        $municipalityId = null;

        if (is_string($city) && $city !== '') {
            $municipality = Municipality::query()->firstOrCreate([
                'name' => $city,
                'province' => $province,
            ]);
            $municipalityId = $municipality->id;
        }

        $attrs = $transformer->transform($raw);

        $boardCode = BoardCode::fromSystemName(
            Arr::get($raw, 'OriginatingSystemName') ?? Arr::get($raw, 'SourceSystemName')
        );
        $mlsNumber = Arr::get($raw, 'ListingId') ?? Arr::get($raw, 'MLSNumber') ?? $key;

        /** @var Listing|null $listing */
        $listing = Listing::withTrashed()->where('external_id', $key)->first();

        if ($listing === null) {
            $listing = Listing::withTrashed()
                ->where('board_code', $boardCode)
                ->where('mls_number', $mlsNumber)
                ->first();
        }

        if ($listing === null) {
            $listing = new Listing;
            $listing->external_id = $key;
        }

        if (method_exists($listing, 'trashed') && $listing->trashed()) {
            $listing->restore();
        }

        $listing->fill(array_filter([
            'municipality_id' => $municipalityId,
            'board_code' => $boardCode,
            'mls_number' => Arr::get($raw, 'ListingId') ?? Arr::get($raw, 'MLSNumber') ?? $key,
            'display_status' => $attrs['status'] ?? null,
            'status_code' => $attrs['status'] ?? null,
            'property_type' => $attrs['property_type'] ?? null,
            'property_style' => $attrs['property_sub_type'] ?? null,
            'street_number' => Arr::get($raw, 'StreetNumber'),
            'street_name' => Arr::get($raw, 'StreetName'),
            'street_address' => $attrs['address'] ?? null,
            'unit_number' => Arr::get($raw, 'UnitNumber'),
            'city' => $attrs['city'] ?? null,
            'province' => $province,
            'postal_code' => $attrs['postal_code'] ?? null,
            'list_price' => $attrs['list_price'] ?? null,
            'original_list_price' => Arr::get($raw, 'OriginalListPrice'),
            'bedrooms' => Arr::get($raw, 'BedroomsTotal'),
            'bathrooms' => Arr::get($raw, 'BathroomsTotalInteger'),
            'days_on_market' => Arr::get($raw, 'DaysOnMarket'),
            'modified_at' => $attrs['modified_at'] ?? null,
            'payload' => $raw,
        ], fn ($v) => $v !== null));

        // Apply source with priority (IDX > VOW). Incoming here is VOW.
        $incomingSource = Source::query()->firstOrCreate([
            'slug' => 'vow',
        ], [
            'name' => 'VOW (PropTx)',
            'type' => 'PROP_TX',
        ]);

        $currentSource = $listing->source_id ? Source::find($listing->source_id) : null;
        $currentSlug = $currentSource?->slug;
        $priority = fn (?string $slug): int => match ($slug) {
            'idx' => 2,
            'vow' => 1,
            default => 0,
        };

        if ($listing->source_id === null || $priority($incomingSource->slug) > $priority($currentSlug)) {
            $listing->source_id = $incomingSource->id;
        }

        // Determine if anything actually changed (ignore noisy JSON payload changes)
        $dirtyKeys = array_keys($listing->getDirty());
        $effectiveDirty = array_values(array_diff($dirtyKeys, ['payload']));
        $statusChanged = in_array('status_code', $dirtyKeys, true) || in_array('display_status', $dirtyKeys, true);

        if ($effectiveDirty === []) {
            // Nothing meaningful changed; skip save and history/media work
            return;
        }

        $listing->save();

        // Record status history only when status fields changed
        if ($statusChanged) {
            $listing->recordStatusHistory($listing->status_code, $listing->display_status, $raw, $listing->modified_at ?? now());
        }

        // Also sync media from the Media resource for this listing key.
        \App\Jobs\SyncIdxMediaForListing::dispatch((int) $listing->id, (string) $key)->onQueue('media');
    }

    // Mapping handled by ListingTransformer

    // Board code normalization handled by App\Support\BoardCode
}
