<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

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

    public function show(string $slug, int $listing): View|RedirectResponse
    {
        $listing = Listing::findOrFail($listing);

        // Redirect to correct slug if wrong slug was used
        if ($listing->slug && $listing->slug !== $slug) {
            return redirect()->to($listing->url, 301);
        }

        if ($listing->isSuppressed()) {
            abort(404);
        }

        $listing->load([
            'media' => fn ($query) => $query->orderBy('position'),
            'source:id,name',
            'municipality:id,name',
            'statusHistory' => fn ($query) => $query->orderByDesc('changed_at')->limit(5),
        ]);

        return view('listings.show', [
            'listing' => $listing,
        ]);
    }
}
