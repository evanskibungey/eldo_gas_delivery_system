<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Service area
    |--------------------------------------------------------------------------
    |
    | The area riders actually cover. Orders whose delivery pin falls outside
    | this circle are rejected at placement.
    |
    | Without this gate an out-of-area order was accepted and charged, and then
    | sat at pending until an admin noticed no rider covered it and cancelled
    | by hand. Failing loudly at checkout is better than failing silently after
    | taking the money.
    |
    | These are the defaults. Ops can override the radius at runtime via the
    | `service_area_radius_km` system setting.
    |
    */

    'service_area' => [
        'name' => env('SERVICE_AREA_NAME', 'Eldoret'),
        'latitude' => (float) env('SERVICE_AREA_LAT', 0.5143),
        'longitude' => (float) env('SERVICE_AREA_LNG', 35.2698),
        'radius_km' => (float) env('SERVICE_AREA_RADIUS_KM', 25),
    ],
];
