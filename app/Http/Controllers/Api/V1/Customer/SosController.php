<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Jobs\SendSmsJob;
use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Customer SOS endpoint. Mirrors the existing web SosController but
 * accepts a JSON body with the live GPS coordinates so the shop
 * manager gets a precise location link, not just the customer's last
 * known address. Per the concept doc §8.3 this is a P0 / safety-
 * critical flow.
 */
class SosController extends Controller
{
    public function trigger(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        $customer = $request->user();

        $lastOrder = $customer
            ->orders()
            ->whereNotIn('status', ['cancelled'])
            ->latest()
            ->first();

        $lat        = $data['lat'] ?? null;
        $lng        = $data['lng'] ?? null;
        $hasGps     = $lat !== null && $lng !== null;
        $mapLink    = $hasGps
            ? "https://maps.google.com/?q={$lat},{$lng}"
            : null;
        $orderRef   = $lastOrder ? "Last order #{$lastOrder->order_number}" : 'No recent order';
        $name       = $customer->name ?? 'Unknown customer';
        $phone      = $customer->phone;

        // Critical log — audit trail.
        Log::critical('[SOS] Gas emergency triggered', [
            'customer_id'   => $customer->id,
            'customer_name' => $name,
            'customer_phone'=> $phone,
            'lat'           => $lat,
            'lng'           => $lng,
            'map_link'      => $mapLink,
            'order_ref'     => $orderRef,
            'ip'            => $request->ip(),
        ]);

        // Inbox row so admins can also see it server-side. Customer-
        // scoped because that's what the existing inbox endpoint
        // queries — this lets the customer see their own SOS history.
        NotificationLog::create([
            'recipient_type' => 'customer',
            'recipient_id'   => $customer->id,
            'channel'        => 'in_app',
            'trigger'        => 'sos.triggered',
            'title'          => 'SOS sent',
            'message'        => $hasGps
                ? 'We have alerted the shop and shared your location.'
                : 'We have alerted the shop. Please call 999 if needed.',
            'data'           => [
                'lat'      => $lat,
                'lng'      => $lng,
                'map_link' => $mapLink,
            ],
            'sent_at'        => now(),
            'created_at'     => now(),
        ]);

        // SMS to shop manager — high priority queue.
        $managerPhone = config('shop.manager_phone');
        if ($managerPhone) {
            $message = "EMERGENCY: {$name} (Tel:{$phone}) reported a gas emergency. ";
            if ($mapLink) {
                $message .= "Location: {$mapLink}. ";
            }
            $message .= "{$orderRef}. Call customer immediately and dispatch safety support.";

            SendSmsJob::dispatch(
                $managerPhone,
                $message,
                'sos_trigger',
                'admin',
                null,
            )->onQueue('high');
        }

        return response()->json([
            'message' => 'Emergency alert sent. Stay safe and call 999.',
        ]);
    }
}
