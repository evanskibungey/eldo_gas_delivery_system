<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CustomerHomeController extends Controller
{
    public function index(): Response
    {
        $customer  = auth('customer')->user();
        $lastOrder = $customer->orders()
            ->with(['size:id,name', 'brand:id,name'])
            ->latest()
            ->first();

        $openHour  = config('shop.open_hour');
        $closeHour = config('shop.close_hour');

        return Inertia::render('Customer/Home', [
            'shopOpen'     => $this->isShopOpen(),
            'shopOpensAt'  => sprintf('%d:00 %s', $openHour > 12 ? $openHour - 12 : $openHour, $openHour >= 12 ? 'PM' : 'AM'),
            'shopClosesAt' => sprintf('%d:00 %s', $closeHour > 12 ? $closeHour - 12 : $closeHour, $closeHour >= 12 ? 'PM' : 'AM'),
            'lastOrder'    => $lastOrder ? [
                'id'           => $lastOrder->id,
                'status'       => $lastOrder->status,
                'size_label'   => $lastOrder->size?->name,
                'brand_name'   => $lastOrder->brand?->name,
                'total_amount' => $lastOrder->total_amount,
                'created_at'   => $lastOrder->created_at->diffForHumans(),
            ] : null,
        ]);
    }

    private function isShopOpen(): bool
    {
        $now   = Carbon::now(config('app.timezone', 'Africa/Nairobi'));
        $open  = $now->copy()->setTime(config('shop.open_hour'), 0);
        $close = $now->copy()->setTime(config('shop.close_hour'), 0);

        return $now->between($open, $close);
    }
}
