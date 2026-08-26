<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GasPointsTransaction;
use App\Models\Order;
use App\Services\GasPointsService;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The delivery thank-you SMS reports points earned and the running balance.
 * Both are read back from the ledger, because one order can credit several rows
 * and because rows carrying this order_id may belong to somebody else entirely.
 */
class DeliveryThankYouPointsTest extends TestCase
{
    use RefreshDatabase;

    private function credit(Customer $customer, Order $order, int $points, string $type = 'earned'): void
    {
        GasPointsTransaction::create([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'type' => $type,
            'points' => $points,
            'balance_after' => max(0, $points),
            'description' => 'test',
            'created_at' => now(),
        ]);
    }

    public function test_it_sums_every_row_the_order_credited(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        // A single order can pay base points, a large-cylinder bonus and a
        // first-delivery bonus as separate rows.
        $this->credit($customer, $order, 50);
        $this->credit($customer, $order, 20);
        $this->credit($customer, $order, 100, 'bonus');

        $this->assertSame(170, app(GasPointsService::class)->earnedForOrder($order));
    }

    public function test_a_referral_bonus_on_this_order_is_not_reported_to_the_buyer(): void
    {
        $buyer = Customer::factory()->create();
        $referrer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $buyer->id]);

        $this->credit($buyer, $order, 50);
        // Same order_id, different customer — this is the referrer's money.
        $this->credit($referrer, $order, 200, 'referral');

        $this->assertSame(50, app(GasPointsService::class)->earnedForOrder($order));
    }

    public function test_redemptions_and_expiries_do_not_reduce_the_reported_earnings(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $this->credit($customer, $order, 50);
        // Stored negative, and an expiry row inherits the bucket's order_id.
        $this->credit($customer, $order, -30, 'redeemed');

        $this->assertSame(50, app(GasPointsService::class)->earnedForOrder($order));
    }

    public function test_the_message_states_the_points_and_the_balance(): void
    {
        $customer = Customer::factory()->create(['name' => 'Evans', 'gaspoints_balance' => 1250]);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $message = app(SmsTemplateService::class)->deliveryThankYou($order, 170, 1250);

        $this->assertStringContainsString('You earned 170 GasPoints', $message);
        $this->assertStringContainsString('balance: 1,250', $message);
        $this->assertStringContainsString('Evans', $message);
    }

    public function test_the_points_sentence_is_omitted_when_nothing_was_earned(): void
    {
        $customer = Customer::factory()->create(['name' => 'Evans']);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        // Happens legitimately (points disabled, order under the minimum) and
        // also if the award job has not finished. Saying nothing beats saying
        // "0 points" or quoting a balance that is about to change.
        $message = app(SmsTemplateService::class)->deliveryThankYou($order, 0, 500);

        $this->assertStringNotContainsString('GasPoints', $message);
        $this->assertStringNotContainsString('balance', $message);
        $this->assertStringContainsString('Thanks for your order', $message);
    }

    public function test_it_fits_in_one_sms_segment_even_for_a_long_name(): void
    {
        // Above 160 characters the carrier bills two messages. The previous
        // wording sat at 161 — one character over — so every delivery cost
        // double. This is the highest-volume customer SMS, so the limit is
        // worth asserting rather than trusting.
        $customer = Customer::factory()->create([
            'name' => 'Christopher Wanjala Kipchumba',
            'gaspoints_balance' => 12500,
        ]);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $message = app(SmsTemplateService::class)->deliveryThankYou($order, 12500, 12500);

        $this->assertLessThanOrEqual(160, strlen($message), "Message spans 2 SMS segments:\n{$message}");
    }

    public function test_only_the_first_name_is_used(): void
    {
        $customer = Customer::factory()->create(['name' => 'Christopher Wanjala Kipchumba']);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $message = app(SmsTemplateService::class)->deliveryThankYou($order, 50, 50);

        $this->assertStringContainsString('Christopher', $message);
        $this->assertStringNotContainsString('Wanjala', $message);
    }

    public function test_a_missing_name_falls_back_without_mangling(): void
    {
        // strtok() on an empty string would produce a broken greeting.
        $customer = Customer::factory()->create(['name' => '']);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $message = app(SmsTemplateService::class)->deliveryThankYou($order, 50, 50);

        $this->assertStringContainsString('valued customer', $message);
    }

    public function test_the_thank_you_is_delayed_so_points_are_credited_first(): void
    {
        // AwardGasPointsOnDelivery listens to the same event on the same queue.
        // Without this delay the SMS can win the race and quote a stale balance.
        $listener = new \App\Listeners\SendDeliveryThankYou();

        $this->assertGreaterThan(0, $listener->delay);
    }
}
