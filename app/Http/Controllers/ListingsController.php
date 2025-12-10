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

        return view('listings.show', [
            'listing' => $listing,
        ]);
    }
}
