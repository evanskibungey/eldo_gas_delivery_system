<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CustomerHomeController extends Controller
{
    public function index(): Response
    {
        $customer = auth('customer')->user();
        $lastOrder = $customer->orders()
            ->with(['size:id,name', 'brand:id,name'])
            ->latest()
            ->first();

        $openTime = SystemSetting::get('shop_open_time', '07:00');
        $closeTime = SystemSetting::get('shop_close_time', '21:00');

        return Inertia::render('Customer/Home', [
            'shopOpen' => $this->isShopOpen($openTime, $closeTime),
            'shopOpensAt' => $this->formatTime($openTime),
            'shopClosesAt' => $this->formatTime($closeTime),
            'lastOrder' => $lastOrder ? [
                'id' => $lastOrder->id,
                'order_type' => $lastOrder->order_type,
                'size_id' => $lastOrder->size_id,
                'brand_id' => $lastOrder->brand_id,
                'status' => $lastOrder->status,
                'payment_status' => $lastOrder->payment_status,
                'size_label' => $lastOrder->size?->name,
                'brand_name' => $lastOrder->brand?->name,
                'total_amount' => $lastOrder->total_amount,
                'created_at' => $lastOrder->created_at->diffForHumans(),
                'can_track' => $lastOrder->isActive() && (bool) $lastOrder->rider_id,
                'can_reorder' => $lastOrder->canBeReorderedByCustomer(),
            ] : null,
        ]);
    }

    private function isShopOpen(string $openTime, string $closeTime): bool
    {
        $tz = config('shop.timezone', 'Africa/Nairobi');
        $now = Carbon::now($tz);
        [$oh, $om] = array_map('intval', explode(':', $openTime));
        [$ch, $cm] = array_map('intval', explode(':', $closeTime));
        $open = $now->copy()->setTime($oh, $om);
        $close = $now->copy()->setTime($ch, $cm);

        return $now->between($open, $close);
    }

    private function formatTime(string $time): string
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $suffix = $hour >= 12 ? 'PM' : 'AM';
        $displayHour = $hour > 12 ? $hour - 12 : ($hour === 0 ? 12 : $hour);

        return $minute > 0
            ? sprintf('%d:%02d %s', $displayHour, $minute, $suffix)
            : sprintf('%d:00 %s', $displayHour, $suffix);
    }
}