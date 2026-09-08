<?php

namespace Tests\Feature;

use App\Jobs\SendSmsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * `sms:test` exists because acceptance is not delivery. TalkSasa answers a send
 * with 202 "accepted" before the carrier has seen it, so a refused sender ID,
 * an empty account and a blocked recipient all look identical to success. The
 * command follows the message to its real conclusion.
 */
class TestSmsCommandTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS_URL = 'https://bulksms.talksasa.test/api/v3/sms/queue/abc-123';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.talksasa.api_token', 'test-token');
        config()->set('services.talksasa.api_url', 'https://bulksms.talksasa.test/send');
        config()->set('services.talksasa.sender_id', 'ELDOGAS');
    }

    /** The real shape: 202, queued, with a URL to follow up on. */
    private function accepted(): array
    {
        return [
            'status' => 'success',
            'message' => 'Your SMS is being processed and will be delivered',
            'data' => ['queue_uid' => 'abc-123', 'status' => 'accepted', 'check_status_url' => self::STATUS_URL],
        ];
    }

    public function test_it_reports_delivery_when_the_carrier_confirms(): void
    {
        Http::fake([
            '*/send' => Http::response($this->accepted(), 202),
            '*/queue/*' => Http::response(['data' => ['status' => 'completed', 'recipient_count' => 1, 'failed_count' => 0, 'total_cost' => 1, 'error' => null]], 200),
        ]);

        $this->artisan('sms:test', ['phone' => '+254700000000'])
            ->expectsOutputToContain('charged')
            ->assertSuccessful();
    }

    public function test_it_catches_a_batch_that_reports_failures(): void
    {
        Http::fake([
            '*/send' => Http::response($this->accepted(), 202),
            '*/queue/*' => Http::response([
                'data' => [
                    'status' => 'completed',
                    'recipient_count' => 1,
                    'failed_count' => 1,
                    'error' => 'Message rejected: source_address filter mismatch',
                ],
            ], 200),
        ]);

        $this->artisan('sms:test', ['phone' => '+254700000000'])
            ->expectsOutputToContain('failed 1 of 1')
            ->assertFailed();
    }

    public function test_completed_with_no_failures_is_not_read_as_a_rejection(): void
    {
        // TalkSasa reports on the BATCH here, and "completed" only means it
        // finished processing. Judging on the status word rather than
        // failed_count made a perfectly good send look like a carrier
        // rejection, and sent us hunting a problem that did not exist.
        Http::fake([
            '*/send' => Http::response($this->accepted(), 202),
            '*/queue/*' => Http::response([
                'data' => [
                    'queue_uid' => 'abc-123',
                    'status' => 'completed',
                    'recipient_count' => 1,
                    'processed_count' => 1,
                    'failed_count' => 0,
                    'total_cost' => 1,
                    'error' => null,
                ],
            ], 200),
        ]);

        $this->artisan('sms:test', ['phone' => '+254700000000'])
            ->doesntExpectOutputToContain('failed')
            ->assertSuccessful();
    }

    public function test_it_fails_loudly_when_the_gateway_refuses_outright(): void
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Invalid sender id'], 200)]);

        $this->artisan('sms:test', ['phone' => '+254700000000'])
            ->expectsOutputToContain('Invalid sender id')
            ->assertFailed();
    }

    public function test_queue_mode_puts_the_job_on_the_bulk_queue(): void
    {
        Queue::fake();

        $this->artisan('sms:test', ['phone' => '+254700000000', '--queue' => true])
            ->assertSuccessful();

        // Campaigns run on `bulk`, so the test has to exercise that queue or it
        // proves nothing about them.
        Queue::assertPushed(SendSmsJob::class, fn ($job) => $job->queue === 'bulk');
    }

    public function test_it_refuses_without_a_number_to_text(): void
    {
        config()->set('shop.manager_phones', '');

        $this->artisan('sms:test')->assertFailed();
    }
}
