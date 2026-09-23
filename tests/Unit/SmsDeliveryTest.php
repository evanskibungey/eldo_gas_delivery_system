<?php

namespace Tests\Unit;

use App\Services\Sms\TalkSasaSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Without credentials the SMS services write the message to a log file and
 * report success. That is right for local work and wrong everywhere else: in
 * production it meant a customer sat on the verification screen waiting for a
 * code that was never sent, pressing resend, with nothing anywhere saying the
 * send had not happened.
 */
class SmsDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_credentials_in_production_are_a_failure(): void
    {
        config(['services.talksasa.api_token' => null]);
        $this->app->detectEnvironment(fn () => 'production');

        $sent = (new TalkSasaSmsService())->send('+254712345678', 'code 1234');

        $this->assertFalse($sent);
    }

    public function test_missing_credentials_locally_still_log_the_message(): void
    {
        config(['services.talksasa.api_token' => null]);
        $this->app->detectEnvironment(fn () => 'local');

        $sent = (new TalkSasaSmsService())->send('+254712345678', 'code 1234');

        // Local development reads the code out of the log, and should not
        // need an SMS account to sign in.
        $this->assertTrue($sent);
    }

    public function test_the_api_admits_it_could_not_send_rather_than_pretending(): void
    {
        config(['services.talksasa.api_token' => null]);
        $this->app->detectEnvironment(fn () => 'production');

        $this->postJson('/api/v1/auth/request-otp', ['phone' => '0712345678'])
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                "We couldn't send a verification code right now. Please try again."
            );

        // And no code is left behind that nobody can receive.
        $this->assertDatabaseCount('otp_tokens', 0);
    }
}
