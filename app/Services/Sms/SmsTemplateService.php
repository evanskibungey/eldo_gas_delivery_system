<?php

namespace App\Services\Sms;

use App\Models\Order;
use App\Models\Rider;
use App\Models\SystemSetting;

/**
 * Centralised SMS message builder.
 * All platform-generated SMS copy lives here — update once, applies everywhere.
 */
class SmsTemplateService
{
    private string $appName;
    private string $appLink;

    public function __construct()
    {
        $this->appName = SystemSetting::get('app_name', 'EldoGas');
        $this->appLink = SystemSetting::get('app_download_url', 'https://bit.ly/eldogas-app');
    }

    // ── Customer templates ────────────────────────────────────────────────────

    /**
     * Sent to the customer immediately after their order is placed.
     */
    public function orderConfirmation(Order $order): string
    {
        $name  = $this->firstName($order);
        $total = 'KES ' . number_format($order->total_amount);

        return "{$this->appName}: Hi {$name}! Order #{$order->order_number} received, "
            . "total {$total}. We are preparing it now. "
            . "Track it on the {$this->appName} app: {$this->appLink}";
    }

    /**
     * Sent to the customer when a rider is assigned and heading their way.
     */
    public function riderAssigned(Order $order, Rider $rider): string
    {
        $riderName = strtok(trim($rider->name), ' ') ?: $rider->name;

        return "{$this->appName}: {$riderName} is on the way with order #{$order->order_number}, "
            . "arriving in under 20 mins. Call {$rider->phone}. "
            . "Track on the {$this->appName} app: {$this->appLink}";
    }

    /**
     * Sent to the customer once the order is marked as delivered (thank-you).
     *
     * The points sentence is omitted entirely when nothing was earned — which
     * happens legitimately (GasPoints disabled, order under the minimum spend)
     * and also when the award job has not finished yet. Reporting "0 points" or
     * a stale balance would be worse than saying nothing, so the caller passes
     * what it actually read from the ledger and this prints only what is true.
     */
    public function deliveryThankYou(Order $order, int $pointsEarned = 0, ?int $pointsBalance = null): string
    {
        $name = $this->firstName($order);

        $points = '';
        if ($pointsEarned > 0) {
            $points = 'You earned ' . number_format($pointsEarned) . ' GasPoints';
            $points .= $pointsBalance !== null
                ? ' (balance: ' . number_format($pointsBalance) . '). '
                : '. ';
        }

        // Kept deliberately tight. The previous wording ran to 161 characters —
        // one past the 160-character limit — so every delivery was billed as
        // two SMS segments. This version carries the points as well and still
        // fits in one, halving the cost of the highest-volume message here.
        return "{$this->appName}: Thanks for your order, {$name}! "
            . $points
            . "Order again on the {$this->appName} app: {$this->appLink}";
    }

    /**
     * Safety tip sent ~10 minutes after delivery.
     */
    public function safetyTip(): string
    {
        // The em dash that used to sit before the sign-off forced this whole
        // message into UCS-2, cutting the segment size from 160 characters to
        // 70 and billing it as four messages. Keep this ASCII.
        return "{$this->appName} Safety: Smell gas? Do NOT touch lights or appliances. "
            . 'Open all windows, leave the building, then call 999 or 0800 723 723.';
    }

    // ── Admin templates ───────────────────────────────────────────────────────

    /**
     * Sent when auto-assignment finds no eligible rider for a new order.
     */
    public function adminNoRiderAvailable(Order $order): string
    {
        // Plain hyphen, not an em dash: one non-GSM-7 character re-encodes the
        // whole message as UCS-2 and triples the segment count.
        return "{$this->appName} ALERT: Order #{$order->order_number} could not be auto-assigned "
            . "- no riders available. Please assign one manually in the admin dashboard.";
    }

    /**
     * Sent to the shop manager when a new order is placed.
     */
    public function adminNewOrder(Order $order): string
    {
        $customer = $order->customer;
        $items    = $this->itemsLine($order);
        $location = $this->locationLine($order);
        $payment  = strtoupper($order->payment_method);
        $total    = 'KES ' . number_format($order->total_amount);

        return "{$this->appName} NEW ORDER #{$order->order_number}"
            . "\nCustomer: {$customer->name}"
            . "\nPhone: {$customer->phone}"
            . "\nItems: {$items}"
            . "\nTotal: {$total} ({$payment})"
            . "\nDeliver to: {$location}";
    }

    // ── Rider templates ───────────────────────────────────────────────────────

    /**
     * Sent to the rider when they are assigned an order.
     */
    public function riderOrderDetails(Order $order, Rider $rider): string
    {
        $customer = $order->customer;
        $items    = $this->itemsLine($order);
        $mapLink  = $this->mapLink($order);
        $payment  = strtoupper($order->payment_method);
        $total    = 'KES ' . number_format($order->total_amount);
        $notes    = $order->delivery_notes ? "\nNote: {$order->delivery_notes}" : '';

        return "{$this->appName} Delivery Assignment"
            . "\nOrder: #{$order->order_number}"
            . "\nCustomer: {$customer->name}"
            . "\nPhone: {$customer->phone}"
            . "\nItems: {$items}"
            . "\nTotal: {$total} ({$payment})"
            . "\nDeliver to: {$mapLink}"
            . $notes;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * First name only. Friendlier in an SMS, and it keeps a long full name from
     * quietly pushing a message past 160 characters into a second billed
     * segment.
     */
    private function firstName(Order $order): string
    {
        $full = trim((string) $order->customer?->name);

        return $full !== '' ? strtok($full, ' ') : 'valued customer';
    }

    private function itemsLine(Order $order): string
    {
        $parts = array_filter([
            $order->size?->name,
            $order->brand?->name,
            $order->order_type === 'swap' ? 'Swap/Refill' : 'New Cylinder',
        ]);

        return implode(', ', $parts);
    }

    private function locationLine(Order $order): string
    {
        if ($order->delivery_lat && $order->delivery_lng) {
            return "https://maps.google.com/?q={$order->delivery_lat},{$order->delivery_lng}";
        }

        return $order->delivery_notes ?? 'No address provided';
    }

    private function mapLink(Order $order): string
    {
        return ($order->delivery_lat && $order->delivery_lng)
            ? "https://maps.google.com/?q={$order->delivery_lat},{$order->delivery_lng}"
            : 'No GPS coordinates';
    }
}
