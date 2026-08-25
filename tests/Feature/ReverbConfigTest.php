<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * env() converts "true"/"false"/"null" into real types but leaves numeric
 * strings as strings. Reverb hands decay_seconds straight to Laravel's
 * RateLimiter, which hands it to Carbon::addRealSeconds() — strictly typed
 * int|float. A plain `REVERB_APP_RATE_LIMIT_DECAY_SECONDS=60` in .env therefore
 * threw on every client ping:
 *
 *   Carbon\Carbon::rawAddUnit(): Argument #3 ($value) must be of type
 *   int|float, string given
 *
 * That killed the connection, so the browser reconnected, pinged, and died
 * again — a permanent loop in which no broadcast ever reached the admin panel.
 *
 * These tests re-evaluate config/reverb.php with env values set the way a real
 * .env file supplies them: as strings.
 */
class ReverbConfigTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $original = [];

    private const VARS = [
        'REVERB_ALLOWED_ORIGINS',
        'REVERB_APP_RATE_LIMIT_DECAY_SECONDS',
        'REVERB_APP_RATE_LIMIT_MAX_ATTEMPTS',
        'REVERB_APP_RATE_LIMITING_ENABLED',
        'REVERB_APP_PING_INTERVAL',
        'REVERB_APP_ACTIVITY_TIMEOUT',
        'REVERB_APP_MAX_CONNECTIONS',
        'REVERB_SERVER_PORT',
        'REVERB_PORT',
    ];

    protected function tearDown(): void
    {
        foreach ($this->original as $key => $value) {
            if ($value === false) {
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }

        parent::tearDown();
    }

    /**
     * Set env vars as strings — exactly how a .env file delivers them — and
     * re-evaluate the config file against them.
     *
     * @param  array<string, string>  $vars
     * @return array<string, mixed>
     */
    private function configWith(array $vars): array
    {
        foreach (self::VARS as $key) {
            if (! array_key_exists($key, $this->original)) {
                $this->original[$key] = $_ENV[$key] ?? false;
            }

            unset($_ENV[$key], $_SERVER[$key]);
        }

        foreach ($vars as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        return require base_path('config/reverb.php');
    }

    public function test_a_string_decay_seconds_becomes_an_int(): void
    {
        $config = $this->configWith([
            'REVERB_APP_RATE_LIMITING_ENABLED' => 'true',
            'REVERB_APP_RATE_LIMIT_DECAY_SECONDS' => '60',
            'REVERB_APP_RATE_LIMIT_MAX_ATTEMPTS' => '60',
        ]);

        $limiting = $config['apps']['apps'][0]['rate_limiting'];

        // The exact value Reverb passes to RateLimiter::increment(), which
        // passes it to Carbon. A string here throws on every client ping.
        $this->assertIsInt($limiting['decay_seconds']);
        $this->assertSame(60, $limiting['decay_seconds']);

        $this->assertIsInt($limiting['max_attempts']);
        $this->assertIsBool($limiting['enabled']);
        $this->assertTrue($limiting['enabled']);
    }

    public function test_string_timing_values_become_ints(): void
    {
        $config = $this->configWith([
            'REVERB_APP_PING_INTERVAL' => '60',
            'REVERB_APP_ACTIVITY_TIMEOUT' => '30',
            'REVERB_SERVER_PORT' => '8080',
            'REVERB_PORT' => '443',
        ]);

        $app = $config['apps']['apps'][0];

        $this->assertIsInt($app['ping_interval']);
        $this->assertIsInt($app['activity_timeout']);
        $this->assertIsInt($app['options']['port']);
        $this->assertSame(8080, $config['servers']['reverb']['port']);
    }

    public function test_unset_max_connections_stays_null_rather_than_zero(): void
    {
        $config = $this->configWith([]);

        // (int) null is 0, which would cap the server at zero connections —
        // every client rejected, for a value nobody set.
        $this->assertNull($config['apps']['apps'][0]['max_connections']);
    }

    public function test_a_scheme_on_allowed_origins_is_stripped(): void
    {
        $config = $this->configWith([
            // Spaces after the commas and a trailing slash — how someone
            // actually types this into the Forge environment editor.
            'REVERB_ALLOWED_ORIGINS' => 'https://eldogas.ke/, http://eldogas.on-forge.com',
        ]);

        // Reverb compares against parse_url($origin, PHP_URL_HOST), so a
        // configured "https://eldogas.ke" never matches the browser's
        // "eldogas.ke" and every connection is rejected as an invalid origin.
        $this->assertSame(
            ['eldogas.ke', 'eldogas.on-forge.com'],
            $config['apps']['apps'][0]['allowed_origins'],
        );
    }

    public function test_the_wildcard_origin_still_works(): void
    {
        $config = $this->configWith([]);

        $this->assertSame(['*'], $config['apps']['apps'][0]['allowed_origins']);
    }
}
