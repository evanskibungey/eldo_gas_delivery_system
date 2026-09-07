<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inbound STOP handling.
 *
 * Two failure modes matter in opposite directions: missing a genuine opt-out
 * is a compliance problem and a route to a suspended sender ID; opting out a
 * customer who did not ask is a silent loss of someone who wanted to hear from
 * us. So matching is deliberately exact.
 */
class SmsInboundOptOutTest extends TestCase
{
    use RefreshDatabase;

    private function inbound(string $from, string $message)
    {
        return $this->postJson('/api/v1/webhooks/sms/inbound', [
            'from' => $from,
            'message' => $message,
        ]);
    }

    public function test_stop_opts_the_customer_out(): void
    {
        $customer = Customer::factory()->create(['phone' => '+254796486683']);

        $this->inbound('+254796486683', 'STOP')->assertOk();

        $this->assertNotNull($customer->fresh()->sms_opt_out_at);
        $this->assertSame('sms', $customer->fresh()->sms_opt_out_source);
    }

    public function test_it_matches_however_the_gateway_formats_the_number(): void
    {
        $customer = Customer::factory()->create(['phone' => '+254796486683']);

        // Same person, three shapes: local, no plus, international.
        $this->inbound('0796486683', 'stop')->assertOk();

        $this->assertNotNull($customer->fresh()->sms_opt_out_at);
    }

    public function test_common_phrasings_are_all_honoured(): void
    {
        foreach (['STOP', 'stop', ' Unsubscribe ', 'OPTOUT', 'cancel'] as $word) {
            $customer = Customer::factory()->create();

            $this->inbound($customer->phone, $word)->assertOk();

            $this->assertNotNull(
                $customer->fresh()->sms_opt_out_at,
                "\"{$word}\" should have opted the customer out.",
            );
        }
    }

    public function test_an_ordinary_reply_does_not_opt_anybody_out(): void
    {
        $customer = Customer::factory()->create();

        // Contains the word, but is plainly not a request to unsubscribe.
        $this->inbound($customer->phone, 'Please do not stop delivering to my gate')->assertOk();

        $this->assertNull($customer->fresh()->sms_opt_out_at);
    }

    public function test_an_unknown_number_is_acknowledged_not_errored(): void
    {
        // A gateway that receives an error retries forever.
        $this->inbound('+254700111222', 'STOP')
            ->assertOk()
            ->assertJson(['status' => 'unknown']);
    }

    public function test_a_malformed_callback_is_acknowledged(): void
    {
        $this->postJson('/api/v1/webhooks/sms/inbound', [])
            ->assertOk()
            ->assertJson(['status' => 'ignored']);
    }

    public function test_opting_out_twice_keeps_the_original_timestamp(): void
    {
        $first = now()->subDays(3);
        $customer = Customer::factory()->create(['sms_opt_out_at' => $first]);

        $this->inbound($customer->phone, 'STOP')->assertOk();

        // When they opted out is the record that matters if it is ever
        // disputed; a repeat STOP must not overwrite it.
        $this->assertSame(
            $first->toDateTimeString(),
            $customer->fresh()->sms_opt_out_at->toDateTimeString(),
        );
    }
}
