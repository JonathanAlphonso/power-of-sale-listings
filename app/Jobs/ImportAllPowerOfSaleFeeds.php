<?php

namespace App\Jobs;

use App\Services\Idx\IdxClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportAllPowerOfSaleFeeds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(public int $pageSize = 500, public int $maxPages = 200) {}

    public function handle(IdxClient $idx): void
    {
        // Import IDX first (higher priority), then VOW
        (new ImportIdxPowerOfSale($this->pageSize, $this->maxPages))->handle($idx);
        (new ImportVowPowerOfSale($this->pageSize, $this->maxPages))->handle($idx);
    }
}
