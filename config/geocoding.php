<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Geocoding
    |--------------------------------------------------------------------------
    |
    | The customer app calls /api/v1/geocode/* rather than a geocoder directly,
    | so the provider can change without shipping an app release, credentials
    | never sit in the APK, and results are cached once for everybody.
    |
    | Drivers:
    |
    |   geoapify   Production. Set GEOCODING_DRIVER=geoapify and
    |              GEOAPIFY_API_KEY. The key stays here and never reaches
    |              the APK.
    |
    |   nominatim  Default, and fine for development. Against the public host
    |              it is not a supportable production configuration: that
    |              host's usage policy forbids the autocomplete search and
    |              per-map-settle reverse lookups this app makes, and will
    |              rate-limit or block real traffic. Point
    |              GEOCODING_NOMINATIM_URL at a self-hosted instance to lift
    |              that entirely.
    |
    */

    'driver' => env('GEOCODING_DRIVER', 'nominatim'),

    'country' => env('GEOCODING_COUNTRY', 'ke'),

    'timeout' => (int) env('GEOCODING_TIMEOUT', 8),

    'user_agent' => env(
        'GEOCODING_USER_AGENT',
        'EldoGas/1.0 (hittydeliverieskenya@gmail.com)'
    ),

    'nominatim' => [
        'url' => env('GEOCODING_NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
    ],

    'opencage' => [
        // Server-side only. Reverse geocoding is what puts a place name on the
        // delivery card in place of raw coordinates.
        'key' => env('OPENCAGE_API_KEY'),
    ],

    'google' => [
        // Server-side only. Keep this distinct from the Maps SDK key the app
        // ships in its manifest: that one is extractable from the APK and is
        // restricted by package name plus signing SHA-1, which does not apply
        // to a key called from a server.
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'geoapify' => [
        // Server-side only. Keep this key distinct from the one the app ships
        // for map tiles: that one is extractable from the APK, and a rotation
        // there must not take geocoding down with it.
        'key' => env('GEOAPIFY_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Addresses do not move. Reverse lookups in particular cluster hard around
    | the same handful of places, so a long TTL removes most upstream traffic.
    | Only successful lookups are cached.
    |
    */

    'cache' => [
        'search_ttl' => (int) env('GEOCODING_SEARCH_TTL', 86400),      // 1 day
        'reverse_ttl' => (int) env('GEOCODING_REVERSE_TTL', 604800),   // 1 week
    ],
];
