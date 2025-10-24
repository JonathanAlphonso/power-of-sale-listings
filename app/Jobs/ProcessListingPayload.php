<?php

namespace App\Jobs;

use App\Models\Listing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessListingPayload implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public array $payload,
        public array $context = [],
    ) {}

    /**
     * Create a new job instance.
     */
    public function handle(): void
    {
        Listing::upsertFromPayload($this->payload, $this->context);
    }
}
