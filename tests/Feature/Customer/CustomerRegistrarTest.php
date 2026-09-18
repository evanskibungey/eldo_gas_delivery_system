<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Services\Customer\CustomerRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The one way a customer record comes into existence.
 *
 * This used to be a private firstOrCreate inside OtpService, which meant an
 * admin taking an order over the phone could not create the caller at all:
 * `customers.referral_code` is NOT NULL UNIQUE and nothing outside that class
 * could mint one.
 *
 * The risk in lifting it out is that the OTP path quietly starts producing a
 * different shape of record, so that is what most of this pins.
 */
class CustomerRegistrarTest extends TestCase
{
    use RefreshDatabase;

    private function registrar(): CustomerRegistrar
    {
        return app(CustomerRegistrar::class);
    }

    public function test_a_new_customer_gets_a_referral_code_and_is_active(): void
    {
        $customer = $this->registrar()->findOrCreateByPhone('0712345678', 'Mercy Chebet');

        $this->assertSame('Mercy Chebet', $customer->name);
        $this->assertTrue((bool) $customer->is_active);
        $this->assertNotEmpty($customer->referral_code);
        $this->assertSame(8, strlen($customer->referral_code));
    }

    public function test_a_local_number_is_normalised_before_it_is_stored(): void
    {
        // customers.phone is UNIQUE. A walk-in entered as 0712345678 and the
        // same person later logging in as +254712345678 must land on one row,
        // or the second insert fails and their history is orphaned from the
        // account they actually end up using.
        $walkIn = $this->registrar()->findOrCreateByPhone('0712345678', 'Mercy', 'admin');
        $this->assertSame('+254712345678', $walkIn->phone);

        $appLogin = $this->registrar()->findOrCreateByPhone('+254712345678', null, 'app', verified: true);

        $this->assertSame($walkIn->id, $appLogin->id);
        $this->assertSame(1, Customer::count());
    }

    public function test_an_existing_customer_keeps_the_name_they_chose(): void
    {
        $existing = Customer::factory()->create([
            'name' => 'Mercy Chebet',
            'phone' => '+254712345678',
        ]);

        // A rushed counter entry must not overwrite it.
        $this->registrar()->findOrCreateByPhone('0712345678', 'M', 'admin');

        $this->assertSame('Mercy Chebet', $existing->fresh()->name);
    }

    public function test_a_blank_name_on_file_is_filled_in(): void
    {
        $existing = Customer::factory()->create(['name' => '', 'phone' => '+254712345678']);

        $this->registrar()->findOrCreateByPhone('0712345678', 'Mercy Chebet', 'admin');

        $this->assertSame('Mercy Chebet', $existing->fresh()->name);
    }

    public function test_only_a_verified_signup_is_marked_verified(): void
    {
        $typedIn = $this->registrar()->findOrCreateByPhone('0712345678', 'Mercy', 'admin');
        $verified = $this->registrar()->findOrCreateByPhone('0722000000', 'Ken', 'app', verified: true);

        // An admin typing a number in does not prove it. This null is the
        // conversion signal the walk-in badge and the campaign filter read.
        $this->assertNull($typedIn->phone_verified_at);
        $this->assertSame('admin', $typedIn->created_via);

        $this->assertNotNull($verified->phone_verified_at);
        $this->assertSame('app', $verified->created_via);
    }

    public function test_referral_codes_do_not_collide(): void
    {
        $codes = [];

        for ($i = 0; $i < 25; $i++) {
            $codes[] = $this->registrar()
                ->findOrCreateByPhone('07120000' . str_pad((string) $i, 2, '0', STR_PAD_LEFT))
                ->referral_code;
        }

        $this->assertCount(25, array_unique($codes));
    }
}
