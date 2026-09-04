<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CylinderSize;
use App\Models\Order;
use App\Models\Rider;
use App\Models\SystemSetting;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SMS is billed per segment, and the segment size depends on the alphabet.
 *
 *   GSM-7  : 160 chars for a single message, 153 each once concatenated
 *   UCS-2  :  70 chars for a single message,  67 each once concatenated
 *
 * A message drops to UCS-2 the moment it contains ONE character outside the
 * GSM-7 set. An em dash, a curly quote or an ellipsis — the sort of thing a
 * word processor inserts silently — more than halves the capacity and can turn
 * a one-segment message into three. Nothing warns you; the bill just grows.
 *
 * This test pins the cost of the customer-facing templates, which are the
 * high-volume ones.
 */
class SmsTemplateLengthTest extends TestCase
{
    use RefreshDatabase;

    /** GSM 03.38 basic character set. */
    private const GSM7 =
        "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡"
        ."ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /** Characters that are GSM-7 but cost two septets each. */
    private const GSM7_EXTENDED = "^{}\\[~]|€";

    /** @return array{gsm7:bool, units:int, segments:int, offenders:array<string>} */
    private function measure(string $message): array
    {
        $basic = preg_split('//u', self::GSM7, -1, PREG_SPLIT_NO_EMPTY);
        $extended = preg_split('//u', self::GSM7_EXTENDED, -1, PREG_SPLIT_NO_EMPTY);
        $chars = preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY);

        $units = 0;
        $offenders = [];

        foreach ($chars as $char) {
            if (in_array($char, $basic, true)) {
                $units++;
            } elseif (in_array($char, $extended, true)) {
                $units += 2;
            } else {
                $offenders[$char] = $char;
            }
        }

        $gsm7 = $offenders === [];

        if (! $gsm7) {
            // Whole message is re-encoded, so length is counted in UTF-16 units.
            $units = mb_strlen($message, 'UTF-8');
            $single = 70;
            $concat = 67;
        } else {
            $single = 160;
            $concat = 153;
        }

        return [
            'gsm7' => $gsm7,
            'units' => $units,
            'segments' => $units <= $single ? 1 : (int) ceil($units / $concat),
            'offenders' => array_values($offenders),
        ];
    }

    private function report(string $label, string $message): array
    {
        $m = $this->measure($message);

        fwrite(STDERR, sprintf(
            "  %-24s %-6s %4d units  %d segment%s%s\n",
            $label,
            $m['gsm7'] ? 'GSM-7' : 'UCS-2',
            $m['units'],
            $m['segments'],
            $m['segments'] > 1 ? 's' : ' ',
            $m['offenders'] ? '   non-GSM7: '.implode(' ', $m['offenders']) : '',
        ));

        return $m;
    }

    private function fixtures(): array
    {
        $size = CylinderSize::first() ?? CylinderSize::factory()->create();

        $customer = Customer::factory()->create(['name' => 'Christopher Wanjala']);
        $rider = Rider::factory()->create(['name' => 'Christopher Kipchumba']);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'size_id' => $size->id,
            'total_amount' => 2450,
            'delivery_notes' => 'Green gate opposite the shop',
        ]);

        return [$order->load(['customer', 'size', 'brand']), $rider];
    }

    public function test_customer_templates_stay_within_their_segment_budget(): void
    {
        [$order, $rider] = $this->fixtures();
        $sms = app(SmsTemplateService::class);

        fwrite(STDERR, "\n\n  CUSTOMER TEMPLATES (high volume)\n");

        $results = [
            'orderConfirmation' => $this->report('orderConfirmation', $sms->orderConfirmation($order)),
            'riderAssigned' => $this->report('riderAssigned', $sms->riderAssigned($order, $rider)),
            'deliveryThankYou' => $this->report('deliveryThankYou', $sms->deliveryThankYou($order, 170, 1250)),
            'safetyTip' => $this->report('safetyTip', $sms->safetyTip()),
        ];

        fwrite(STDERR, "\n  ADMIN / RIDER TEMPLATES (low volume, detail matters)\n");

        // These carry an address, a map link and a phone number, so two
        // segments is an accepted trade. They still must not slip into UCS-2.
        $detail = [
            'adminNewOrder' => $this->report('adminNewOrder', $sms->adminNewOrder($order)),
            'adminNoRiderAvailable' => $this->report('adminNoRiderAvailable', $sms->adminNoRiderAvailable($order)),
            'riderOrderDetails' => $this->report('riderOrderDetails', $sms->riderOrderDetails($order, $rider)),
        ];
        fwrite(STDERR, "\n");

        foreach ($results + $detail as $name => $result) {
            $this->assertTrue(
                $result['gsm7'],
                "{$name} contains non-GSM-7 characters (".implode(' ', $result['offenders'])
                ."), which cuts the segment size from 160 to 70 and multiplies the cost. "
                .'Use a plain hyphen instead of a dash, and straight quotes.',
            );
        }

        // These carry the /get short link (22 chars) rather than the full Play
        // Store URL (68), which is what keeps them inside one segment. Swapping
        // the raw store URL back in costs a second segment on each — three more
        // billed messages per delivery.
        $budget = [
            'orderConfirmation' => 1,
            'riderAssigned' => 1,
            'deliveryThankYou' => 1,
            // No link, but the full safety instruction set does not fit in 160.
            // Deliberately not trimmed: see SmsTemplateService::safetyTip().
            'safetyTip' => 2,
        ];

        foreach ($results as $name => $result) {
            $this->assertLessThanOrEqual(
                $budget[$name],
                $result['segments'],
                "{$name} now spans {$result['segments']} SMS segments ({$result['units']} units), "
                ."over its budget of {$budget[$name]}. This goes to every customer, so each "
                .'extra segment is billed on every order.',
            );
        }
    }

    public function test_the_raw_store_url_would_cost_a_second_segment_each(): void
    {
        [$order, $rider] = $this->fixtures();

        // Prices the decision rather than asserting it in a comment: put the
        // full store URL back and every message carrying it needs two segments.
        SystemSetting::set(
            'app_download_url',
            'https://play.google.com/store/apps/details?id=co.ke.eldogas.customer',
        );
        $sms = new SmsTemplateService();

        foreach ([
            'orderConfirmation' => $sms->orderConfirmation($order),
            'riderAssigned' => $sms->riderAssigned($order, $rider),
            'deliveryThankYou' => $sms->deliveryThankYou($order, 170, 1250),
        ] as $name => $message) {
            $this->assertSame(
                2,
                $this->measure($message)['segments'],
                "{$name} was expected to need two segments with the raw store URL.",
            );
        }
    }
}
