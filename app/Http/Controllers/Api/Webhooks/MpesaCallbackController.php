<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaCallbackController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Receive Daraja STK Push callback from Safaricom.
     * No auth middleware — Safaricom calls this endpoint directly.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->input('Body.stkCallback');

        if (! $payload) {
            Log::warning('M-Pesa callback: missing Body.stkCallback', $request->all());
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $checkoutRequestId  = $payload['CheckoutRequestID']  ?? null;
        $merchantRequestId  = $payload['MerchantRequestID']  ?? null;
        $resultCode         = $payload['ResultCode']          ?? -1;

        Log::info('M-Pesa callback received', [
            'CheckoutRequestID' => $checkoutRequestId,
            'ResultCode'        => $resultCode,
        ]);

        if (! $checkoutRequestId) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $order = Order::where('mpesa_checkout_request_id', $checkoutRequestId)
            ->orWhere('mpesa_merchant_request_id', $merchantRequestId)
            ->first();

        if (! $order) {
            Log::warning('M-Pesa callback: no order found', compact('checkoutRequestId', 'merchantRequestId'));
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        if ((int) $resultCode === 0) {
            // Payment successful — mark as collected if not already.
            if ($order->payment_status !== 'collected') {
                $order->update(['payment_status' => 'collected']);
                Log::info("M-Pesa payment auto-confirmed for order #{$order->order_number}");
            }
        } else {
            // Payment failed or cancelled by user.
            Log::warning("M-Pesa payment failed for order #{$order->order_number}", [
                'ResultCode' => $resultCode,
                'ResultDesc' => $payload['ResultDesc'] ?? '',
            ]);
        }

        // Safaricom expects this exact response shape.
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
