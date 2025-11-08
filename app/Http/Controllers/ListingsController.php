<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Contracts\View\View;

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

        return view('listings.show', [
            'listing' => $listing,
        ]);
    }
}
