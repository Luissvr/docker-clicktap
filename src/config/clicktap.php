<?php

return [
    'media_disk' => env('CLICKTAP_MEDIA_DISK', 'public'),
    'local_catalog' => env('CLICKTAP_LOCAL_CATALOG', false),
    'sync_cache_store' => env('CLICKTAP_SYNC_CACHE_STORE', 'file'),
    'sync_interval_seconds' => 1200,
];
