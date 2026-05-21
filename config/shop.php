<?php

return [
    'open_hour'     => (int) env('SHOP_OPEN_HOUR', 7),
    'close_hour'    => (int) env('SHOP_CLOSE_HOUR', 20),
    'timezone'      => env('SHOP_TIMEZONE', 'Africa/Nairobi'),
    'manager_phones' => env('SHOP_MANAGER_PHONES', ''),
];
