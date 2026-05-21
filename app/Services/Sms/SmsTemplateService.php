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
        $name  = $order->customer->name ?: 'valued customer';
        $total = 'KES ' . number_format($order->total_amount);

        return "{$this->appName}: Hi {$name}! Your order #{$order->order_number} has been received "
            . "and is being processed. Total: {$total}. "
            . "Download the {$this->appName} app to track your delivery: {$this->appLink}";
    }

    /**
     * Sent to the customer when a rider is assigned and heading their way.
     */
    public function riderAssigned(Order $order, Rider $rider): string
    {
        return "{$this->appName}: Great news! {$rider->name} is heading your way with your gas. "
            . "Order #{$order->order_number}. Expected in ~25 mins. "
            . "Contact rider: {$rider->phone}. "
            . "Track live on the {$this->appName} app: {$this->appLink}";
    }

    /**
     * Sent to the customer once the order is marked as delivered (thank-you).
     */
    public function deliveryThankYou(Order $order): string
    {
        $name = $order->customer->name ?: 'valued customer';

        return "{$this->appName}: Thank you for ordering your gas with {$this->appName}, {$name}! "
            . "It was our pleasure serving you. "
            . "Order again anytime on the {$this->appName} app: {$this->appLink}";
    }

    /**
     * Safety tip sent ~10 minutes after delivery.
     */
    public function safetyTip(): string
    {
        return "Safety Tip from {$this->appName}: Smell gas? Do NOT switch on lights or appliances. "
            . 'Open all windows, leave the building immediately, and call 999 or 0800 723 723. '
            . "Stay safe — {$this->appName} Team. App: {$this->appLink}";
    }

    // ── Admin templates ───────────────────────────────────────────────────────

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
