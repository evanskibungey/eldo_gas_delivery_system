<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Rider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiderManagementTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::factory()->create();
    }

    private function actingAsAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    public function test_admin_created_rider_is_available_for_assignment_immediately(): void
    {
        $response = $this->actingAsAdmin()->post(route('admin.riders.store'), [
            'name' => 'Jane Rider',
            'phone' => '+254712345678',
            'is_active' => true,
        ]);

        $rider = Rider::firstOrFail();

        $response->assertRedirect(route('admin.riders.show', $rider));

        $this->assertDatabaseHas('riders', [
            'id' => $rider->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        $order = Order::factory()->create(['status' => 'pending']);

        $this->actingAsAdmin()
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Orders/Show')
                ->has('availableRiders', 1)
                ->where('availableRiders.0.id', $rider->id)
                ->where('availableRiders.0.name', 'Jane Rider')
            );
    }

    public function test_inactive_rider_created_by_admin_starts_unavailable(): void
    {
        $this->actingAsAdmin()->post(route('admin.riders.store'), [
            'name' => 'Offline Rider',
            'phone' => '+254712345679',
            'is_active' => false,
            'is_available' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('riders', [
            'phone' => '+254712345679',
            'is_active' => false,
            'is_available' => false,
        ]);
    }
}