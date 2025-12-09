<?php

return [
    // Storage disk for downloaded listing media.
    'disk' => env('MEDIA_DISK', 'public'),

    // Base path prefix for stored media files.
    'path_prefix' => env('MEDIA_PATH_PREFIX', 'listings'),

    // Auto-queue downloads when new media records are created via payload sync.
    'auto_download' => (bool) env('MEDIA_AUTO_DOWNLOAD', false),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for media API calls and downloads to avoid
    | overwhelming the IDX API or storage system. The IDX API allows
    | 60,000 requests/minute, but we use conservative defaults.
    |
    */

    // Max IDX API requests per minute for media sync jobs (fetching media metadata)
    'rate_limit_api' => (int) env('MEDIA_RATE_LIMIT_API', 120),

    // Max download requests per minute (fetching actual image files)
    'rate_limit_download' => (int) env('MEDIA_RATE_LIMIT_DOWNLOAD', 60),
];
