<?php

namespace App\Services\GoogleAnalytics;

use App\Services\GoogleAnalytics\Exceptions\CredentialsException;
use App\Services\GoogleAnalytics\Exceptions\TokenException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;

class AccessTokenProvider
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache
    ) {}

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieve(array $credentials): string
    {
        $clientEmail = Arr::get($credentials, 'client_email');
        $privateKey = Arr::get($credentials, 'private_key');
        $tokenUri = Arr::get($credentials, 'token_uri', 'https://oauth2.googleapis.com/token');

        if (! is_string($clientEmail) || $clientEmail === '') {
            throw new CredentialsException('The service account client email is missing.');
        }

        if (! is_string($privateKey) || $privateKey === '') {
            throw new CredentialsException('The service account private key is missing.');
        }

        if (! is_string($tokenUri) || $tokenUri === '') {
            throw new CredentialsException('The token URI is missing.');
        }

        $cacheKey = sprintf('analytics:token:%s', hash('sha256', $clientEmail.$tokenUri));

        return $this->cache->remember($cacheKey, now()->addMinutes(55), function () use ($clientEmail, $privateKey, $tokenUri): string {
            $assertion = $this->createAssertion($clientEmail, $privateKey, $tokenUri);

            $response = $this->http->asForm()->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if (! $response->successful()) {
                throw new TokenException('Failed to retrieve an access token from Google Analytics.');
            }

            $accessToken = $response->json('access_token');

            if (! is_string($accessToken) || $accessToken === '') {
                throw new TokenException('The Google Analytics token response did not contain an access token.');
            }

            return $accessToken;
        });
    }

    private function createAssertion(string $clientEmail, string $privateKey, string $tokenUri): string
    {
        $now = CarbonImmutable::now();

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
            'aud' => $tokenUri,
            'exp' => $now->addMinutes(60)->getTimestamp(),
            'iat' => $now->getTimestamp(),
        ], JSON_THROW_ON_ERROR));

        $data = $header.'.'.$payload;

        $privateKeyResource = openssl_pkey_get_private($privateKey);

        if ($privateKeyResource === false) {
            throw new CredentialsException('The provided private key is invalid.');
        }

        $signature = '';

        $signed = openssl_sign($data, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);

        if ($signed === false) {
            throw new CredentialsException('Failed to sign the access token assertion.');
        }

        openssl_free_key($privateKeyResource);

        return $data.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
