<?php

return [
    'media_disk' => env('CLICKTAP_MEDIA_DISK', 'public'),
    'local_catalog' => env('CLICKTAP_LOCAL_CATALOG', false),
    'sync_cache_store' => env('CLICKTAP_SYNC_CACHE_STORE', 'file'),
    'publication_store' => env('CLICKTAP_PUBLICATION_STORE', 'publications'),
    'publication_slugs' => [
        'fefigas', 'la-patito', 'panaderia-el-pastelito', 'pollos-school',
        'carnes-san-andres', 'centro-medico-veterinario', 'electricidad-automotriz',
        'barber-brothers', 'automotriz-briceno', 'ralph-service', 'koniko-can-can',
        'san-daniel', 'taller-80s', 'estacion-vip', 'motomundi', 'ralph-service-whatsapp',
        'lipigas-benito-henriquez', 'moreno-munoz', '10-car', 'celmi-motos',
        'radiadores-el-indio', 'henri-car', 'alta-bandera',
    ],
    'sync_interval_seconds' => 1200,
];
