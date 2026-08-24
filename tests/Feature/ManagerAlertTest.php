<?php

namespace Tests\Feature;

use App\Jobs\SendSmsJob;
use App\Models\Customer;
use App\Support\ManagerContacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Manager alerting used to read a `shop.manager_phone` key that was never
 * defined, so four alert paths — including the P0 SOS emergency — silently
 * paged nobody. These lock in that every configured manager is reached.
 */
class ManagerAlertTest extends TestCase
{
    use RefreshDatabase;

    // ─── Contact resolution ─────────────────────────────────────────────────

    public function test_local_numbers_are_normalized_to_international_format(): void
    {
        config(['shop.manager_phones' => '0712345678, +254798765432']);

        $this->assertSame(['+254712345678', '+254798765432'], ManagerContacts::phones());
    }

    public function test_blank_configuration_yields_no_contacts(): void
    {
        config(['shop.manager_phones' => '']);
        $this->assertSame([], ManagerContacts::phones());

        config(['shop.manager_phones' => '   ']);
        $this->assertSame([], ManagerContacts::phones());
    }

    public function test_duplicate_and_empty_entries_are_discarded(): void
    {
        config(['shop.manager_phones' => '0712345678,,0712345678, ,+254798765432']);

        $this->assertSame(['+254712345678', '+254798765432'], ManagerContacts::phones());
    }

    // ─── The P0 path ────────────────────────────────────────────────────────

    public function test_sos_pages_every_configured_manager(): void
    {
        Queue::fake();
        config(['shop.manager_phones' => '0712345678,0798765432']);

        $customer = Customer::factory()->create(['name' => 'Jane Doe']);
        $token = $customer->createToken('mobile')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/sos/trigger', [
            'lat' => -0.2833,
            'lng' => 35.2697,
        ])->assertOk();

        Queue::assertPushed(SendSmsJob::class, 2);

        foreach (['+254712345678', '+254798765432'] as $expected) {
            Queue::assertPushed(SendSmsJob::class, function (SendSmsJob $job) use ($expected): bool {
                return $this->phoneOf($job) === $expected;
            });
        }
    }

    public function test_sos_still_succeeds_for_the_customer_when_no_manager_is_configured(): void
    {
        Queue::fake();
        config(['shop.manager_phones' => '']);

        $customer = Customer::factory()->create();
        $token = $customer->createToken('mobile')->plainTextToken;

        // The customer must never see an error because of a server-side
        // misconfiguration — it is logged instead.
        $this->withToken($token)->postJson('/api/v1/sos/trigger', [
            'lat' => -0.2833,
            'lng' => 35.2697,
        ])->assertOk();

        Queue::assertNotPushed(SendSmsJob::class);
    }

    public function test_sos_records_the_alert_for_the_customer_inbox(): void
    {
        Queue::fake();
        config(['shop.manager_phones' => '0712345678']);

        $customer = Customer::factory()->create();
        $token = $customer->createToken('mobile')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/sos/trigger', [
            'lat' => -0.2833,
            'lng' => 35.2697,
        ])->assertOk();

        $this->assertDatabaseHas('notifications_log', [
            'recipient_type' => 'customer',
            'recipient_id'   => $customer->id,
            'trigger'        => 'sos.triggered',
        ]);
    }

    /**
     * SendSmsJob keeps its constructor arguments private, so read the phone
     * back through reflection rather than widening the job's API for a test.
     */
    private function phoneOf(SendSmsJob $job): string
    {
        $property = new \ReflectionProperty($job, 'phone');
        $property->setAccessible(true);

        return (string) $property->getValue($job);
    }
}
