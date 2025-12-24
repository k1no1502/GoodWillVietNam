<?php
return [
    'warehouse' => [
        'name' => 'Kho hàng Goodwill',
        'address' => '328 Ngô Quyền, Sơn Trà, Đà Nẵng, Việt Nam',
        'place_id' => null,
        // Fixed coordinates (absolute). Prefer setting via Google picker in admin/warehouse-location.php
        'lat' => 16.047079,
        'lng' => 108.206230,
    ],
    'simulation' => [
        'auto_deliver_enabled' => true,
        'auto_deliver_min_seconds' => 30,
    ],
];
