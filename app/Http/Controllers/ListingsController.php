<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\ListingView;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class ListingsController extends Controller
{
    public function index(): View
    {
        $listings = Listing::query()
            ->visible()
            ->with(['source:id,name', 'municipality:id,name', 'media'])
            ->latest('modified_at')
            ->paginate(12);

        return view('listings', [
            'listings' => $listings,
        ]);
    }

    public function show(Listing $listing): View
    {
        if ($listing->isSuppressed()) {
            abort(404);
        }

        $listing->load([
            'media' => fn ($query) => $query->orderBy('position'),
            'source:id,name',
            'municipality:id,name',
            'statusHistory' => fn ($query) => $query->orderByDesc('changed_at')->limit(5),
        ]);

        // Record the view for authenticated users
        if (Auth::check()) {
            ListingView::recordView(Auth::user(), $listing);
        }

        // Find related listings based on similar characteristics
        $relatedListings = $this->getRelatedListings($listing);

        return view('listings.show', [
            'listing' => $listing,
            'relatedListings' => $relatedListings,
        ]);
    }

    /**
     * Get related listings based on similar characteristics.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Listing>
     */
    private function getRelatedListings(Listing $listing): \Illuminate\Database\Eloquent\Collection
    {
        // Build a query for similar listings
        $query = Listing::query()
            ->visible()
            ->where('id', '!=', $listing->id)
            ->with(['media'])
            ->limit(4);

        // Priority 1: Same municipality and similar price range (±30%)
        $priceMin = $listing->list_price ? $listing->list_price * 0.7 : null;
        $priceMax = $listing->list_price ? $listing->list_price * 1.3 : null;

        if ($listing->municipality_id) {
            $query->where(function ($q) use ($listing, $priceMin, $priceMax) {
                $q->where('municipality_id', $listing->municipality_id);
                if ($priceMin && $priceMax) {
                    $q->whereBetween('list_price', [$priceMin, $priceMax]);
                }
            });
        } elseif ($listing->city) {
            // Fallback to same city
            $query->where('city', $listing->city);
            if ($priceMin && $priceMax) {
                $query->whereBetween('list_price', [$priceMin, $priceMax]);
            }
        } elseif ($priceMin && $priceMax) {
            // Fallback to just price range
            $query->whereBetween('list_price', [$priceMin, $priceMax]);
        }

        // Order by most recently updated
        $query->orderByDesc('modified_at');

        $related = $query->get();

        // If we don't have enough results, fetch more with looser criteria
        if ($related->count() < 4) {
            $remaining = 4 - $related->count();
            $excludeIds = $related->pluck('id')->push($listing->id)->toArray();

            $additional = Listing::query()
                ->visible()
                ->whereNotIn('id', $excludeIds)
                ->with(['media'])
                ->orderByDesc('modified_at')
                ->limit($remaining)
                ->get();

            $related = $related->concat($additional);
        }

        return $related;
    }
}
