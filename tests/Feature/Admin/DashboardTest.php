<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Rider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_loads_with_correct_props(): void
    {
        $admin = Admin::factory()->create();

        Order::factory()->count(2)->create(['status' => 'pending']);
        Order::factory()->delivered()->count(3)->create([
            'created_at'   => now(),
            'total_amount' => 2000,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard/Index')
                ->has('metrics')
                ->has('dailyRevenue')
                ->has('bySize')
                ->has('recentOrders')
                ->has('topRiders')
                ->has('stock')
                ->where('metrics.pending_orders', 2)
            );
    }

    public function test_unauthenticated_access_redirects(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect();
    }
}
