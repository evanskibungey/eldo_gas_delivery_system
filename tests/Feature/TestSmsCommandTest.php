<?php

namespace Tests\Feature;

use App\Jobs\SendSmsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TestSmsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.talksasa.api_token', 'test-token');
        config()->set('services.talksasa.api_url', 'https://bulksms.talksasa.test/send');
        config()->set('services.talksasa.sender_id', 'ELDOGAS');
    }

    public function test_it_reports_success_when_the_gateway_accepts(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success'], 202)]);

        $this->artisan('sms:test', ['phone' => '+254700000000'])
            ->assertSuccessful();
    }

    public function test_it_fails_loudly_when_the_gateway_refuses(): void
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
        config()->set('services.shop.manager_phones', '');

        $this->artisan('sms:test')->assertFailed();
    }
}
