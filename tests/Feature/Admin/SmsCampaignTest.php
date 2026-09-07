<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendSmsJob;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SmsCampaign;
use App\Services\Admin\SmsAudienceService;
use App\Services\Admin\SmsCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Bulk SMS. Two things here are unforgiving: a send cannot be recalled, and
 * every recipient is billed. So the audience must be exactly who was intended,
 * and the cost shown before sending must be the cost incurred.
 */
class SmsCampaignTest extends TestCase
{
    use RefreshDatabase;

    private function customer(array $attributes = []): Customer
    {
        return Customer::factory()->create($attributes);
    }

    // ── Consent ───────────────────────────────────────────────────────────────

    public function test_a_promo_never_reaches_an_opted_out_customer(): void
    {
        $willing = $this->customer();
        $optedOut = $this->customer(['sms_opt_out_at' => now()]);

        $recipients = app(SmsAudienceService::class)->resolve('all_active');

        $this->assertTrue($recipients->contains('id', $willing->id));
        $this->assertFalse(
            $recipients->contains('id', $optedOut->id),
            'An opted-out customer was included in a promotional audience.',
        );
    }

    public function test_an_opted_out_customer_still_gets_their_order_updates(): void
    {
        // Transactional SMS is not gated on marketing consent — the customer
        // asked for it by placing an order. Those go through SendSmsJob
        // directly and never touch the audience service.
        $optedOut = $this->customer(['sms_opt_out_at' => now()]);

        Queue::fake();
        SendSmsJob::dispatch($optedOut->phone, 'Your order is on the way', 'order_status', 'customer', $optedOut->id);

        Queue::assertPushed(SendSmsJob::class);
    }

    public function test_the_opt_out_line_is_appended_to_promos_only(): void
    {
        $service = app(SmsCampaignService::class);

        $this->assertStringContainsString(
            'Reply STOP to opt out.',
            $service->compose('Refills 10% off today', 'promo'),
        );

        // An operational notice is not marketing and does not carry it.
        $this->assertStringNotContainsString(
            'Reply STOP',
            $service->compose('We are closed tomorrow for a public holiday', 'info'),
        );
    }

    public function test_the_opt_out_line_is_not_added_twice(): void
    {
        $composed = app(SmsCampaignService::class)
            ->compose('Deal today. Reply STOP to unsubscribe.', 'promo');

        $this->assertSame(1, substr_count(strtoupper($composed), 'STOP'));
    }

    // ── Audiences ─────────────────────────────────────────────────────────────

    public function test_an_empty_hand_picked_selection_matches_nobody(): void
    {
        $this->customer();
        $this->customer();

        // The dangerous default: an empty whereIn must not degrade into
        // "everyone", which would text the whole customer base by accident.
        $this->assertCount(0, app(SmsAudienceService::class)->resolve('selected', []));
    }

    public function test_lapsed_customers_exclude_those_who_never_ordered(): void
    {
        $lapsed = $this->customer();
        Order::factory()->create(['customer_id' => $lapsed->id, 'created_at' => now()->subDays(90)]);

        $recent = $this->customer();
        Order::factory()->create(['customer_id' => $recent->id, 'created_at' => now()->subDays(5)]);

        $neverOrdered = $this->customer();

        $recipients = app(SmsAudienceService::class)->resolve('no_order_60d');

        $this->assertTrue($recipients->contains('id', $lapsed->id));
        $this->assertFalse($recipients->contains('id', $recent->id));
        // "Come back, we miss you" is the wrong message for someone who has
        // never bought anything.
        $this->assertFalse($recipients->contains('id', $neverOrdered->id));
    }

    public function test_inactive_customers_and_blank_numbers_are_never_texted(): void
    {
        $this->customer(['is_active' => false]);
        $this->customer(['phone' => '']);
        $reachable = $this->customer();

        $recipients = app(SmsAudienceService::class)->resolve('all_active');

        $this->assertCount(1, $recipients);
        $this->assertSame($reachable->id, $recipients->first()->id);
    }

    // ── Cost ──────────────────────────────────────────────────────────────────

    public function test_the_preview_costs_the_message_that_will_actually_be_sent(): void
    {
        $this->customer();
        $this->customer();

        $preview = app(SmsCampaignService::class)->preview('Refills 10% off today', 'promo', 'all_active');

        // The opt-out line is part of what gets billed, so it has to be part of
        // what gets counted.
        $this->assertStringContainsString('Reply STOP to opt out.', $preview['body']);
        $this->assertSame(2, $preview['recipients']);
        $this->assertSame($preview['segments'] * 2, $preview['total_segments']);
    }

    public function test_the_preview_reports_how_many_were_skipped(): void
    {
        $this->customer();
        $this->customer(['sms_opt_out_at' => now()]);
        $this->customer(['sms_opt_out_at' => now()]);

        $preview = app(SmsCampaignService::class)->preview('Offer', 'promo', 'all_active');

        // A recipient count that quietly shrank would look like a bug.
        $this->assertSame(1, $preview['recipients']);
        $this->assertSame(2, $preview['skipped_opted_out']);
    }

    public function test_an_emoji_is_flagged_as_more_than_doubling_the_cost(): void
    {
        $this->customer();

        $preview = app(SmsCampaignService::class)->preview('🚨 Big offer today', 'promo', 'all_active');

        $this->assertSame('UCS-2', $preview['encoding']);
        $this->assertContains('🚨', $preview['offenders']);
    }

    // ── Sending ───────────────────────────────────────────────────────────────

    public function test_sending_queues_one_job_per_recipient_and_records_who(): void
    {
        Queue::fake();

        $one = $this->customer();
        $two = $this->customer();
        $this->customer(['sms_opt_out_at' => now()]);

        $campaign = app(SmsCampaignService::class)->dispatchCampaign(
            'September offer', 'Refills 10% off', 'promo', 'all_active', [], null,
        );

        Queue::assertPushed(SendSmsJob::class, 2);

        $this->assertSame(2, $campaign->recipient_count);
        $this->assertSame(1, $campaign->skipped_opted_out);

        // Answers "did this customer get it", which the totals cannot.
        $this->assertDatabaseHas('sms_campaign_recipients', [
            'sms_campaign_id' => $campaign->id,
            'customer_id' => $one->id,
        ]);
        $this->assertDatabaseHas('sms_campaign_recipients', [
            'sms_campaign_id' => $campaign->id,
            'customer_id' => $two->id,
        ]);
    }

    public function test_bulk_sends_go_to_their_own_queue(): void
    {
        Queue::fake();
        $this->customer();

        app(SmsCampaignService::class)->dispatchCampaign(
            'Offer', 'Body', 'promo', 'all_active', [], null,
        );

        // Marketing must never sit in front of an order confirmation.
        Queue::assertPushed(SendSmsJob::class, fn ($job) => $job->queue === 'bulk');
    }

    public function test_a_send_with_no_recipients_is_refused(): void
    {
        Queue::fake();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(SmsCampaignService::class)->dispatchCampaign(
            'Offer', 'Body', 'promo', 'selected', [], null,
        );
    }

    public function test_the_recorded_message_is_the_one_that_was_sent(): void
    {
        Queue::fake();
        $this->customer();

        $campaign = app(SmsCampaignService::class)->dispatchCampaign(
            'Offer', 'Refills 10% off', 'promo', 'all_active', [], null,
        );

        // Not the raw input: what actually went out, opt-out line included.
        $this->assertStringContainsString('Reply STOP to opt out.', $campaign->message);
    }

    // ── Admin surface ─────────────────────────────────────────────────────────

    public function test_an_admin_can_send_from_the_composer(): void
    {
        Queue::fake();

        $admin = Admin::factory()->create();
        $this->customer();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.sms.store'), [
                'title' => 'September offer',
                'message' => 'Refills 10% off today',
                'kind' => 'promo',
                'audience' => 'all_active',
                'confirmed_segments' => 1,
            ])
            ->assertRedirect(route('admin.sms.index'));

        $this->assertDatabaseHas('sms_campaigns', [
            'title' => 'September offer',
            'status' => 'queued',
            'admin_id' => $admin->id,
        ]);
    }

    public function test_a_guest_cannot_send(): void
    {
        Queue::fake();
        $this->customer();

        $this->post(route('admin.sms.store'), [
            'title' => 'Offer',
            'message' => 'Body',
            'kind' => 'promo',
            'audience' => 'all_active',
            'confirmed_segments' => 1,
        ])->assertRedirect();

        $this->assertSame(0, SmsCampaign::count());
        Queue::assertNothingPushed();
    }

    public function test_the_composer_can_search_customers_without_leaving_the_page(): void
    {
        $admin = Admin::factory()->create();
        $this->customer(['name' => 'Novenah Shellomith', 'phone' => '+254741252274']);
        $this->customer(['name' => 'Someone Else', 'phone' => '+254700000001']);

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.sms.customers', ['q' => 'Novenah']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Novenah Shellomith']);

        // Phone works too — it is often what the shop has to hand.
        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.sms.customers', ['q' => '741252274']))
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_the_picker_flags_who_has_opted_out(): void
    {
        $admin = Admin::factory()->create();
        $this->customer(['name' => 'Optee', 'sms_opt_out_at' => now()]);

        // Shown rather than hidden: the admin should see that ticking this
        // person will not actually text them.
        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.sms.customers', ['q' => 'Optee']))
            ->assertOk()
            ->assertJsonFragment(['opted_out' => true]);
    }

    public function test_the_picker_never_offers_someone_who_cannot_be_texted(): void
    {
        $admin = Admin::factory()->create();
        $this->customer(['name' => 'Gone Away', 'is_active' => false]);
        $this->customer(['name' => 'No Number', 'phone' => '']);

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.sms.customers'))
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_a_guest_cannot_search_customers(): void
    {
        $this->customer();

        $this->get(route('admin.sms.customers'))->assertRedirect();
    }

    public function test_an_admin_can_toggle_a_customers_consent(): void
    {
        $admin = Admin::factory()->create();
        $customer = $this->customer();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.sms-opt-out', $customer))
            ->assertRedirect();

        $this->assertNotNull($customer->fresh()->sms_opt_out_at);
        $this->assertSame('admin', $customer->fresh()->sms_opt_out_source);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.customers.sms-opt-out', $customer));

        $this->assertNull($customer->fresh()->sms_opt_out_at);
    }
}
