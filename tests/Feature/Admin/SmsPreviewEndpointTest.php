<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsPreviewEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_returns_a_recipient_count_for_a_hand_picked_selection(): void
    {
        $admin = Admin::factory()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.sms.preview'), [
                'message' => 'TEST ELDOGAS SENDERID',
                'kind' => 'promo',
                'audience' => 'selected',
                'customer_ids' => [$customer->id],
            ]);

        $response->assertOk();
        $this->assertSame(1, $response->json('recipients'));
    }

    public function test_preview_works_for_an_audience(): void
    {
        $admin = Admin::factory()->create();
        Customer::factory()->count(3)->create();

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.sms.preview'), [
                'message' => 'Hello',
                'kind' => 'promo',
                'audience' => 'all_active',
            ])
            ->assertOk()
            ->assertJsonPath('recipients', 3);
    }

    public function test_preview_handles_an_empty_message(): void
    {
        // The composer fires this on first render, before anything is typed.
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.sms.preview'), [
                'message' => '',
                'kind' => 'promo',
                'audience' => 'all_active',
            ])
            ->assertOk();
    }
}
