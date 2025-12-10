<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Listing;
use App\Models\SavedSearch;
use App\Notifications\NewListingsMatchedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSavedSearchNotifications implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public string $frequency = 'instant',
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $searches = SavedSearch::query()
            ->where('is_active', true)
            ->where('notification_channel', '!=', 'none')
            ->where('notification_frequency', $this->frequency)
            ->with('user')
            ->cursor();

        $processedCount = 0;
        $notifiedCount = 0;

        foreach ($searches as $search) {
            /** @var SavedSearch $search */
            if ($search->user === null) {
                continue;
            }

            $processedCount++;

            $newListings = $this->findNewListings($search);

            if ($newListings->isEmpty()) {
                $search->update([
                    'last_ran_at' => now(),
                    'next_run_at' => $this->calculateNextRun($search->notification_frequency),
                ]);

                continue;
            }

            $search->user->notify(new NewListingsMatchedNotification($search, $newListings));

            $search->update([
                'last_ran_at' => now(),
                'last_matched_at' => now(),
                'next_run_at' => $this->calculateNextRun($search->notification_frequency),
            ]);

            $notifiedCount++;

            Log::info('Saved search notification sent', [
                'saved_search_id' => $search->id,
                'user_id' => $search->user_id,
                'listings_count' => $newListings->count(),
            ]);
        }

        Log::info('Saved search notifications processed', [
            'frequency' => $this->frequency,
            'processed' => $processedCount,
            'notified' => $notifiedCount,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Listing>
     */
    protected function findNewListings(SavedSearch $search): \Illuminate\Support\Collection
    {
        $filters = $search->filters ?? [];
        $lastRanAt = $search->last_ran_at;

        $query = Listing::query()
            ->visible()
            ->with(['media' => fn ($q) => $q->where('is_primary', true)->limit(1)])
            ->when($lastRanAt !== null, fn (Builder $q) => $q->where('created_at', '>', $lastRanAt));

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        // Keyword search
        if (! empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('mls_number', 'like', '%' . $search . '%')
                    ->orWhere('street_address', 'like', '%' . $search . '%')
                    ->orWhere('city', 'like', '%' . $search . '%')
                    ->orWhere('postal_code', 'like', '%' . $search . '%');
            });
        }

        // Status filter
        if (! empty($filters['status'])) {
            $query->where('display_status', $filters['status']);
        }

        // Municipality filter
        if (! empty($filters['municipality'])) {
            $query->where('municipality_id', (int) $filters['municipality']);
        }

        // Property type filter
        if (! empty($filters['type'])) {
            $query->where('property_type', $filters['type']);
        }

        // Price filters
        if (! empty($filters['min_price'])) {
            $minPrice = (float) preg_replace('/[^0-9.]/', '', (string) $filters['min_price']);
            if ($minPrice > 0) {
                $query->where('list_price', '>=', $minPrice);
            }
        }

        if (! empty($filters['max_price'])) {
            $maxPrice = (float) preg_replace('/[^0-9.]/', '', (string) $filters['max_price']);
            if ($maxPrice > 0) {
                $query->where('list_price', '<=', $maxPrice);
            }
        }

        // Bedroom filter
        if (! empty($filters['beds'])) {
            $beds = (int) $filters['beds'];
            if ($beds > 0) {
                $query->where('bedrooms', '>=', $beds);
            }
        }

        // Bathroom filter
        if (! empty($filters['baths'])) {
            $baths = (float) $filters['baths'];
            if ($baths > 0) {
                $query->where('bathrooms', '>=', $baths);
            }
        }
    }

    protected function calculateNextRun(string $frequency): \Carbon\Carbon
    {
        return match ($frequency) {
            'instant' => now()->addMinutes(5),
            'daily' => now()->addDay()->setTime(8, 0),
            'weekly' => now()->addWeek()->startOfWeek()->setTime(8, 0),
            default => now()->addDay(),
        };
    }
}
