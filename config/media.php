<?php

return [
    // Storage disk for downloaded listing media.
    'disk' => env('MEDIA_DISK', 'public'),

    // Base path prefix for stored media files.
    'path_prefix' => env('MEDIA_PATH_PREFIX', 'listings'),

    // Auto-queue downloads when new media records are created via payload sync.
    'auto_download' => (bool) env('MEDIA_AUTO_DOWNLOAD', false),
];
