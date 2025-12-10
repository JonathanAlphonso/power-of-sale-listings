<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class NewListingsMatchedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Listing>  $listings
     */
    public function __construct(
        public SavedSearch $savedSearch,
        public Collection $listings,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($this->savedSearch->notification_channel === 'none') {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->listings->count();
        $searchName = $this->savedSearch->name;

        $message = (new MailMessage)
            ->subject(trans_choice(
                ':count new listing matches ":search"|:count new listings match ":search"',
                $count,
                ['count' => $count, 'search' => $searchName]
            ))
            ->greeting(__('New listings found!'))
            ->line(trans_choice(
                'We found :count new listing that matches your saved search ":search".|We found :count new listings that match your saved search ":search".',
                $count,
                ['count' => $count, 'search' => $searchName]
            ));

        // Add up to 5 listings to the email
        foreach ($this->listings->take(5) as $listing) {
            /** @var Listing $listing */
            $price = $listing->list_price !== null
                ? '$' . number_format((float) $listing->list_price)
                : 'Price N/A';

            $address = $listing->street_address ?? 'Address unavailable';
            $city = $listing->city ?? '';
            $type = $listing->property_type ?? 'Property';

            $message->line("**{$address}**")
                ->line("{$type} in {$city} - {$price}");
        }

        if ($count > 5) {
            $message->line(__('...and :remaining more.', ['remaining' => $count - 5]));
        }

        return $message
            ->action(__('View all matching listings'), route('listings.index', $this->savedSearch->filters ?? []))
            ->line(__('You can manage your saved searches and notification preferences in your account settings.'))
            ->salutation(__('Happy house hunting!'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'saved_search_id' => $this->savedSearch->id,
            'saved_search_name' => $this->savedSearch->name,
            'listings_count' => $this->listings->count(),
            'listing_ids' => $this->listings->pluck('id')->toArray(),
        ];
    }
}
