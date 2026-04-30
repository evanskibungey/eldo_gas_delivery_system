<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Jobs\SendSmsJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SosController extends Controller
{
    public function trigger(Request $request): RedirectResponse
    {
        $customer = auth('customer')->user();

        $lastOrder = $customer
            ?->orders()
            ->whereNotIn('status', ['cancelled'])
            ->latest()
            ->first();

        $orderRef   = $lastOrder ? "Last order #{$lastOrder->order_number}" : 'No recent order';
        $name       = $customer?->name ?? 'Unknown customer';
        $phone      = $customer?->phone ?? 'N/A';
        $customerId = $customer?->id ?? null;

        Log::critical('[SOS] Gas emergency triggered', [
            'customer_id'    => $customerId,
            'customer_name'  => $name,
            'customer_phone' => $phone,
            'order_ref'      => $orderRef,
            'ip'             => $request->ip(),
        ]);

        $managerPhone = config('shop.manager_phone');

        if ($managerPhone) {
            $message = "EMERGENCY: {$name} (Tel:{$phone}) reported a gas emergency. "
                . "{$orderRef}. Call customer immediately and dispatch safety support.";

            SendSmsJob::dispatch($managerPhone, $message, 'sos_trigger', 'admin', null)
                ->onQueue('high');
        }

        return back()->with('sos_sent', true);
    }
}
