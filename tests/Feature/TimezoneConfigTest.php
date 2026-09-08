<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * config/app.php had 'timezone' hardcoded to 'UTC' while .env and .env.example
 * both set APP_TIMEZONE=Africa/Nairobi. The env var was therefore ignored and
 * everything — order times, the SMS bodies that quote a time, every admin
 * screen — ran three hours behind the shop's actual clock.
 *
 * Nothing errored. It just looked like the server was in the wrong place.
 */
class TimezoneConfigTest extends TestCase
{
    public function test_the_app_timezone_is_configurable_from_the_environment(): void
    {
        $config = require base_path('config/app.php');

        // The value itself varies by environment; what matters is that the
        // env var is consulted at all.
        $this->assertNotSame(
            'UTC',
            $config['timezone'],
            'config/app.php is ignoring APP_TIMEZONE again.',
        );
    }

    public function test_php_and_carbon_agree_on_the_configured_zone(): void
    {
        // A mismatch here means date() and now() disagree, which produces
        // timestamps that are right in one place and wrong in another.
        $this->assertSame(config('app.timezone'), date_default_timezone_get());
    }
}
