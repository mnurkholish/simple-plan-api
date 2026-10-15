<?php

return [
    'scan_base_url' => env(
        'ASSET_SCAN_BASE_URL',
        env('APP_URL') . '/assets'
    ),
];
