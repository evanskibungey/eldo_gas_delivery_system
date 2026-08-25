<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\CylinderSize;
use App\Models\GasPointsTransaction;
use App\Models\Order;
use App\Models\OrderRating;
use App\Models\OrderStatusHistory;
use App\Models\Rider;
use App\Models\StockAuditLog;
use App\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `orders:purge` clears test trading data before going live. The risk is not
 * the delete itself — it is the denormalised counters that would otherwise keep
 * reporting totals for orders that no longer exist.
 */
class PurgeOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_changes_nothing(): void
    {
        Order::factory()->count(3)->create();

        $this->artisan('orders:purge', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(3, Order::count());
    }

    public function test_it_deletes_orders_and_their_children(): void
    {
        $order = Order::factory()->create(['status' => 'delivered']);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'delivered',
            'actor_type' => 'admin',
            'created_at' => now(),
        ]);

        $this->artisan('orders:purge', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Order::count());
        $this->assertSame(0, DB::table('order_status_history')->count());
        $this->assertSame(0, DB::table('order_addons')->count());
    }

    public function test_customers_riders_and_catalogue_survive(): void
    {
        $customer = Customer::factory()->create();
        $rider = Rider::factory()->create();
        $size = CylinderSize::first() ?? CylinderSize::factory()->create();

        Order::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'rider_id' => $rider->id,
        ]);

        $this->artisan('orders:purge', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('riders', ['id' => $rider->id]);
        $this->assertDatabaseHas('cylinder_sizes', ['id' => $size->id]);
    }

    public function test_rider_counters_are_zeroed(): void
    {
        $rider = Rider::factory()->create([
            'total_deliveries' => 17,
            'avg_rating' => 4.75,
            'incident_count' => 3,
        ]);

        $order = Order::factory()->create([
            'rider_id' => $rider->id,
            'status' => 'delivered',
        ]);
        OrderRating::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'rider_id' => $rider->id,
            'stars' => 5,
            'created_at' => now(),
        ]);

        $this->artisan('orders:purge', ['--force' => true])->assertSuccessful();

        $rider->refresh();
        $this->assertSame(0, $rider->total_deliveries);
        $this->assertEquals(0, (float) $rider->avg_rating);
        $this->assertSame(0, $rider->incident_count);
    }

    public function test_order_earned_points_are_removed_and_balances_recomputed(): void
    {
        $customer = Customer::factory()->create(['gaspoints_balance' => 150]);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        // Earned on a test order — must go.
        GasPointsTransaction::create([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'type' => 'earned',
            'points' => 100,
            'balance_after' => 100,
            'description' => 'Test order',
            'created_at' => now(),
        ]);

        // A standalone bonus with no order behind it — must survive.
        GasPointsTransaction::create([
            'customer_id' => $customer->id,
            'order_id' => null,
            'type' => 'bonus',
            'points' => 50,
            'balance_after' => 150,
            'description' => 'Signup bonus',
            'created_at' => now(),
        ]);

        $this->artisan('orders:purge', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, GasPointsTransaction::count());
        $this->assertSame(50, (int) $customer->fresh()->gaspoints_balance);
        // The surviving row's running total is rewritten, not left at 150.
        $this->assertSame(50, (int) GasPointsTransaction::first()->balance_after);
    }

    public function test_keep_points_leaves_balances_untouched(): void
    {
        $customer = Customer::factory()->create(['gaspoints_balance' => 100]);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        GasPointsTransaction::create([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'type' => 'earned',
            'points' => 100,
            'balance_after' => 100,
            'description' => 'Test order',
            'created_at' => now(),
        ]);

        $this->artisan('orders:purge', ['--force' => true, '--keep-points' => true])
            ->assertSuccessful();

        $this->assertSame(1, GasPointsTransaction::count());
        $this->assertSame(100, (int) $customer->fresh()->gaspoints_balance);
        // FK is nullOnDelete, so the row survives with a dangling order_id nulled.
        $this->assertNull(GasPointsTransaction::first()->order_id);
    }

    public function test_manual_stock_adjustments_survive_but_order_entries_go(): void
    {
        $size = CylinderSize::first() ?? CylinderSize::factory()->create();
        $order = Order::factory()->create(['size_id' => $size->id]);

        StockAuditLog::create([
            'size_id' => $size->id,
            'change_type' => 'auto_deduction',
            'change_amount' => -1,
            'new_count' => 9,
            'order_id' => $order->id,
            'created_at' => now(),
        ]);

        StockAuditLog::create([
            'size_id' => $size->id,
            'change_type' => 'manual_adjustment',
            'change_amount' => 10,
            'new_count' => 10,
            'order_id' => null,
            'created_at' => now(),
        ]);

        $this->artisan('orders:purge', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, StockAuditLog::count());
        $this->assertSame('manual_adjustment', StockAuditLog::first()->change_type);
    }

    public function test_restore_stock_adds_back_what_the_orders_deducted(): void
    {
        $size = CylinderSize::first() ?? CylinderSize::factory()->create();

        StockLevel::updateOrCreate(
            ['size_id' => $size->id],
            ['filled_count' => 7, 'empty_count' => 0, 'low_stock_threshold' => 5],
        );

        foreach (Order::factory()->count(3)->create(['size_id' => $size->id]) as $order) {
            StockAuditLog::create([
                'size_id' => $size->id,
                'change_type' => 'auto_deduction',
                'change_amount' => -1,
                'new_count' => 7,
                'order_id' => $order->id,
                'created_at' => now(),
            ]);
        }

        $this->artisan('orders:purge', ['--force' => true, '--restore-stock' => true])
            ->assertSuccessful();

        $this->assertSame(10, (int) StockLevel::where('size_id', $size->id)->value('filled_count'));
    }

    public function test_stock_is_left_alone_without_the_flag(): void
    {
        $size = CylinderSize::first() ?? CylinderSize::factory()->create();

        StockLevel::updateOrCreate(
            ['size_id' => $size->id],
            ['filled_count' => 7, 'empty_count' => 0, 'low_stock_threshold' => 5],
        );

        Order::factory()->create(['size_id' => $size->id]);

        $this->artisan('orders:purge', ['--force' => true])->assertSuccessful();

        $this->assertSame(7, (int) StockLevel::where('size_id', $size->id)->value('filled_count'));
    }
}
