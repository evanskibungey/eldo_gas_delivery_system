<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /get is the short download link every customer SMS carries.
 *
 * It exists for cost: SMS is billed per 160-character segment, the full Play
 * Store URL is 68 characters, and three of the four messages sent per delivery
 * carry it. The short link keeps each of them inside one segment.
 *
 * If this route ever breaks, nothing fails loudly — the SMS still sends, and
 * the customer just lands on a 404 instead of the app.
 */
class AppDownloadLinkTest extends TestCase
{
    use RefreshDatabase;

    private const STORE_URL = 'https://play.google.com/store/apps/details?id=co.ke.eldogas.customer';

    public function test_it_redirects_to_the_play_store(): void
    {
        $this->get('/get')->assertRedirect(self::STORE_URL);
    }

    public function test_the_destination_is_changeable_without_a_deploy(): void
    {
        SystemSetting::set('play_store_url', 'https://example.test/new-listing');

        $this->get('/get')->assertRedirect('https://example.test/new-listing');
    }

    public function test_the_redirect_is_temporary(): void
    {
        // 301 would be cached by browsers indefinitely, stranding anyone who
        // followed an old SMS after the store listing moves.
        $this->get('/get')->assertStatus(302);
    }

    public function test_it_needs_no_login(): void
    {
        // Sent by SMS to people who by definition do not have the app yet.
        $this->get('/get')->assertRedirect(self::STORE_URL);
    }

    public function test_the_sms_templates_use_it(): void
    {
        $link = app(SmsTemplateService::class)->safetyTip();

        // safetyTip carries no link; the others do. Assert via a template that
        // does, so this fails if the default ever drifts back to a raw URL.
        $confirmation = app(SmsTemplateService::class)
            ->orderConfirmation(\App\Models\Order::factory()->create());

        $this->assertStringContainsString(url('/get'), $confirmation);
        $this->assertStringNotContainsString('play.google.com', $confirmation);
        $this->assertStringNotContainsString('play.google.com', $link);
    }

    public function test_it_does_not_sit_under_the_reverb_websocket_path(): void
    {
        // Nginx proxies `location ^~ /app/` straight to Reverb. A download link
        // under /app would reach the socket server on any request that carried
        // a trailing slash, and fail for some people and not others.
        $this->assertStringNotContainsString('/app', parse_url(url('/get'), PHP_URL_PATH));
    }
}
