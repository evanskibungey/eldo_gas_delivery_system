<?php

namespace Tests\Feature\Admin;

use App\Events\TestOrderAlertEvent;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TestOrderAlertCommandTest extends TestCase
{
    public function test_it_broadcasts_as_a_new_order_on_the_admin_channel(): void
    {
        Event::fake([TestOrderAlertEvent::class]);

        $this->artisan('orders:test-alert')->assertSuccessful();

        Event::assertDispatched(TestOrderAlertEvent::class, function (TestOrderAlertEvent $event) {
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());

            // Must land on the same channel and under the same event name a real
            // order uses, or it proves nothing about the real path.
            $this->assertContains('private-admin.orders', $channels);
            $this->assertSame('order.placed', $event->broadcastAs());

            $payload = $event->broadcastWith();

            $this->assertFalse($payload['is_reoffer'], 'A reoffer is skipped by the panel.');
            $this->assertArrayHasKey('customer_name', $payload);
            $this->assertArrayHasKey('address', $payload);
            $this->assertArrayHasKey('total_amount', $payload);

            return true;
        });
    }

    public function test_each_run_uses_a_fresh_id_so_it_is_not_deduplicated(): void
    {
        Event::fake([TestOrderAlertEvent::class]);

        $this->artisan('orders:test-alert')->assertSuccessful();
        $this->artisan('orders:test-alert')->assertSuccessful();

        $ids = [];
        Event::assertDispatched(TestOrderAlertEvent::class, function (TestOrderAlertEvent $event) use (&$ids) {
            $ids[] = $event->broadcastWith()['id'];

            return true;
        });

        // The panel announces each order id once. Repeated ids would make the
        // second test run look like a failure when it was merely deduplicated.
        $this->assertCount(2, array_unique($ids));
    }
}
