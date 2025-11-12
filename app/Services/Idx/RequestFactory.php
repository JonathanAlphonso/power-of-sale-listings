<?php

declare(strict_types=1);

namespace App\Services\Idx;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class RequestFactory
{
    public function idx(): PendingRequest
    {
        return Http::retry(3, 500)
            ->timeout(60)
            ->baseUrl(rtrim((string) config('services.idx.base_uri', ''), '/'))
            ->withToken((string) config('services.idx.token', ''))
            ->acceptJson()
            ->withHeaders([
                'OData-Version' => '4.0',
            ]);
    }

    public function vow(): PendingRequest
    {
        return Http::retry(3, 500)
            ->timeout(60)
            ->baseUrl(rtrim((string) config('services.vow.base_uri', ''), '/'))
            ->withToken((string) config('services.vow.token', ''))
            ->acceptJson()
            ->withHeaders([
                'OData-Version' => '4.0',
            ]);
    }

    public function idxProperty(bool $preferMaxPage = true): PendingRequest
    {
        $req = $this->idx();

        return $preferMaxPage
            ? $req->withHeaders(['Prefer' => 'odata.maxpagesize=500'])
            : $req;
    }

    public function vowProperty(bool $preferMaxPage = true): PendingRequest
    {
        $req = $this->vow();

        return $preferMaxPage
            ? $req->withHeaders(['Prefer' => 'odata.maxpagesize=500'])
            : $req;
    }
}
