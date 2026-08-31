<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rider location broadcast throttling
    |--------------------------------------------------------------------------
    |
    | Every location ping writes to the riders table (the admin map and the
    | assignment picker need a fresh position), but broadcasting each one fans
    | out to the admin map
    | plus one channel per active order. These thresholds decide which pings
    | are worth putting on the wire.
    |
    | Read from config rather than system_settings so the hot path stays free
    | of an extra query per ping.
    |
    */

    // Hard rate cap: never broadcast more often than this, per rider.
    'min_broadcast_interval_seconds' => (int) env('RIDER_TRACKING_MIN_INTERVAL', 5),

    // Movement below this is treated as GPS jitter, not travel.
    'min_broadcast_distance_meters' => (int) env('RIDER_TRACKING_MIN_DISTANCE', 25),

    // Keep-alive so a stationary rider's marker does not look stale.
    'broadcast_heartbeat_seconds' => (int) env('RIDER_TRACKING_HEARTBEAT', 30),

    /*
    |--------------------------------------------------------------------------
    | Rider acceptance window
    |--------------------------------------------------------------------------
    |
    | How long a rider has to accept an order an admin assigned them before it
    | is returned to the pending queue for reassignment. The sweeper runs every 30 seconds, so the window a rider
    | actually experiences is this value plus up to 30 seconds.
    |
    */

    'acceptance_window_seconds' => (int) env('RIDER_ACCEPTANCE_WINDOW', 60),
];
