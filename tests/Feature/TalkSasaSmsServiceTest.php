<?php

namespace Tests\Feature;

use App\Jobs\SendSmsJob;
use App\Services\Sms\SmsServiceInterface;
use App\Services\Sms\TalkSasaSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TalkSasa answers a rejected message with HTTP 200 and an error in the body.
 *
 * Trusting the HTTP status alone made every refusal look like a success: the
 * send returned true, notifications_log recorded sent_at, nothing was written
 * to the log file and nothing reached failed_jobs. An unregistered sender ID
 * could stop all delivery with no visible symptom anywhere in the system.
 */
class TalkSasaSmsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.talksasa.api_token', 'test-token');
        config()->set('services.talksasa.api_url', 'https://bulksms.talksasa.test/api/v3/sms/send');
        config()->set('services.talksasa.sender_id', 'ELDOGAS');
    }

    public function test_a_rejected_sender_id_is_a_failure_despite_the_200(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'error',
                'message' => 'Invalid sender id',
            ], 200),
        ]);

        $service = new TalkSasaSmsService();

        $this->assertFalse($service->send('+254700000000', 'Test'));
        $this->assertSame('Invalid sender id', $service->lastError());
    }

    public function test_the_real_queued_response_is_a_success(): void
    {
        // The exact shape production returns: 202, not 200, and the message is
        // only queued. Worth pinning — a stricter reading of "successful" that
        // demanded 200, or that treated data.status "accepted" as not-success,
        // would fail every SMS the gateway actually took.
        Http::fake([
            '*' => Http::response([
                'status' => 'success',
                'message' => 'Your SMS is being processed and will be delivered',
                'data' => [
                    'queue_uid' => '298d0a69-f4f1-4631-88c0-d9108f3bf256',
                    'status' => 'accepted',
                    'recipients_count' => 1,
                    'sms_count' => 1,
                    'estimated_cost' => 1,
                ],
            ], 202),
        ]);

        $service = new TalkSasaSmsService();

        $this->assertTrue($service->send('+254796486683', 'Test'));
        $this->assertNull($service->lastError());
    }

    public function test_a_successful_send_is_reported_as_success(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'success', 'data' => ['id' => 'abc']], 200),
        ]);

        $service = new TalkSasaSmsService();

        $this->assertTrue($service->send('+254700000000', 'Test'));
        $this->assertNull($service->lastError());
    }

    public function test_a_body_without_a_status_field_is_not_treated_as_failure(): void
    {
        // Some endpoints return only the queued message on success. An absent
        // status must not be read as an error, or every SMS would "fail".
        Http::fake([
            '*' => Http::response(['data' => ['id' => 'abc']], 200),
        ]);

        $this->assertTrue((new TalkSasaSmsService())->send('+254700000000', 'Test'));
    }

    public function test_a_non_json_body_falls_back_to_the_http_status(): void
    {
        // A proxy error page rather than the API.
        Http::fake(['*' => Http::response('<html>502</html>', 502)]);

        $service = new TalkSasaSmsService();

        $this->assertFalse($service->send('+254700000000', 'Test'));
        $this->assertStringContainsString('502', (string) $service->lastError());
    }

    public function test_an_http_error_is_a_failure(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $service = new TalkSasaSmsService();

        $this->assertFalse($service->send('+254700000000', 'Test'));
        $this->assertSame('Unauthenticated.', $service->lastError());
    }

    public function test_the_gateways_reason_is_recorded_against_the_notification(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'error', 'message' => 'Invalid sender id'], 200),
        ]);

        $this->app->bind(SmsServiceInterface::class, TalkSasaSmsService::class);

        try {
            // The job deliberately fails so the queue retries it.
            (new SendSmsJob('+254700000000', 'Test', 'test_trigger', 'admin', 0))
                ->handle($this->app->make(SmsServiceInterface::class));
        } catch (\Throwable) {
            // Swallowed: the recorded log is what matters here.
        }

        // Support should see the cause in the database, not just in a log file
        // on the server.
        $this->assertDatabaseHas('notifications_log', [
            'trigger' => 'test_trigger',
            'error' => 'Invalid sender id',
        ]);
    }

    public function test_the_sender_id_is_actually_sent(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

        (new TalkSasaSmsService())->send('+254700000000', 'Test');

        Http::assertSent(fn ($request) => $request['sender_id'] === 'ELDOGAS');
    }
}
