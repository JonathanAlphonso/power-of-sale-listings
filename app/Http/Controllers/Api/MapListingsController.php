<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Support\ListingPresentation;
use App\Support\PropertyTypeAbbreviations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapListingsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $listings = Listing::query()
            ->visible()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            // Search
            ->when($request->query('q'), function (Builder $query, string $search): void {
                $query->where(function (Builder $q) use ($search): void {
                    $q->where('mls_number', 'like', '%'.$search.'%')
                        ->orWhere('street_address', 'like', '%'.$search.'%')
                        ->orWhere('city', 'like', '%'.$search.'%')
                        ->orWhere('public_remarks', 'like', '%'.$search.'%');
                });
            })
            // Status (array)
            ->when($request->query('status'), fn (Builder $q, $v) => $q->whereIn('display_status', (array) $v))
            // Property class & type (arrays)
            ->when($request->query('class'), fn (Builder $q, $v) => $q->whereIn('property_class', (array) $v))
            ->when($request->query('type'), fn (Builder $q, $v) => $q->whereIn('property_type', (array) $v))
            // Price range
            ->when($request->query('price_min'), fn (Builder $q, $v) => $q->where('list_price', '>=', (float) $v))
            ->when($request->query('price_max'), fn (Builder $q, $v) => $q->where('list_price', '<=', (float) $v))
            // Beds & Baths (arrays)
            ->when($request->query('beds'), fn (Builder $q, $v) => $this->buildRoomQuery($q, 'bedrooms', (array) $v))
            ->when($request->query('baths'), fn (Builder $q, $v) => $this->buildRoomQuery($q, 'bathrooms', (array) $v))
            // Square footage
            ->when($request->query('sqft_min'), fn (Builder $q, $v) => $q->where('square_feet', '>=', (int) $v))
            ->when($request->query('sqft_max'), fn (Builder $q, $v) => $q->where('square_feet', '<=', (int) $v))
            // Lot size
            ->when($request->query('lot_min'), fn (Builder $q, $v) => $q->where('lot_size_area', '>=', (float) $v))
            ->when($request->query('lot_max'), fn (Builder $q, $v) => $q->where('lot_size_area', '<=', (float) $v))
            // Financial
            ->when($request->query('tax_max'), fn (Builder $q, $v) => $q->where('tax_annual_amount', '<=', (float) $v))
            ->when($request->query('fee_max'), fn (Builder $q, $v) => $q->where('association_fee', '<=', (float) $v))
            // Age (array)
            ->when($request->query('age'), fn (Builder $q, $v) => $q->whereIn('approximate_age', (array) $v))
            // Location (arrays)
            ->when($request->query('city'), fn (Builder $q, $v) => $q->whereIn('city', (array) $v))
            ->when($request->query('municipality'), fn (Builder $q, $v) => $q->whereIn('municipality_id', array_map('intval', (array) $v)))
            // Listed since
            ->when($request->query('listed_since'), fn (Builder $q, $v) => $q->where('listed_at', '>=', $v))
            ->with(['media' => fn ($q) => $q->orderByDesc('is_primary')->limit(1)])
            ->select([
                'id',
                'slug',
                'latitude',
                'longitude',
                'list_price',
                'display_status',
                'listed_at',
                'mls_number',
                'property_type',
                'property_class',
                'street_address',
                'city',
                'bedrooms',
                'bathrooms',
                'square_feet',
            ])
            ->get()
            ->map(fn (Listing $listing) => [
                'id' => $listing->id,
                'slug' => $listing->slug,
                'lat' => (float) $listing->latitude,
                'lng' => (float) $listing->longitude,
                'price' => $listing->list_price,
                'priceFormatted' => ListingPresentation::currency($listing->list_price),
                'priceShort' => $this->formatPriceShort($listing->list_price),
                'status' => $listing->display_status,
                'statusColor' => ListingPresentation::statusBadge($listing->display_status),
                'listedAt' => $listing->listed_at?->format('M j, Y'),
                'mlsNumber' => $listing->mls_number,
                'propertyType' => $listing->property_type,
                'typeCode' => PropertyTypeAbbreviations::get($listing->property_type),
                'address' => $listing->street_address,
                'city' => $listing->city,
                'beds' => $listing->bedrooms,
                'baths' => $listing->bathrooms,
                'sqft' => $listing->square_feet,
                'url' => $listing->url,
                'thumbnail' => $listing->media->first()?->public_url,
            ]);

        return response()->json(['listings' => $listings]);
    }

    private function buildRoomQuery(Builder $query, string $column, array $values): void
    {
        if (empty($values)) {
            return;
        }

        $query->where(function (Builder $q) use ($column, $values): void {
            foreach ($values as $value) {
                $isMinimum = str_ends_with($value, '+');
                $number = $isMinimum ? rtrim($value, '+') : $value;

                if (is_numeric($number)) {
                    $q->orWhere($column, $isMinimum ? '>=' : '=', (int) $number);
                }
            }
        });
    }

    private function formatPriceShort(?float $price): string
    {
        if ($price === null) {
            return 'N/A';
        }

        if ($price >= 1000000) {
            return '$'.number_format($price / 1000000, 1).'M';
        }

        if ($price >= 1000) {
            return '$'.number_format($price / 1000, 0).'K';
        }

        return '$'.number_format($price, 0);
    }
}
