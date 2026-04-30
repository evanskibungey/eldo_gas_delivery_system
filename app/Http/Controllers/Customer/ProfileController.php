<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $customer = auth('customer')->user();
        $customer->load(['addresses', 'orders' => fn ($q) => $q->latest()->limit(5)->with(['size', 'brand'])]);

        $recentOrders = $customer->orders->map(fn ($o) => [
            'id'           => $o->id,
            'order_number' => $o->order_number,
            'status'       => $o->status,
            'size_name'    => $o->size?->name,
            'brand_name'   => $o->brand?->name,
            'total_amount' => $o->total_amount,
            'created_at'   => $o->created_at->format('d M Y'),
        ]);

        $addresses = $customer->addresses->map(fn ($a) => [
            'id'          => $a->id,
            'label'       => $a->label,
            'description' => $a->description,
            'is_default'  => (bool) $a->is_default,
            'latitude'    => $a->latitude,
            'longitude'   => $a->longitude,
        ]);

        return Inertia::render('Customer/Profile/Show', [
            'customer' => [
                'id'              => $customer->id,
                'name'            => $customer->name,
                'phone'           => $customer->phone,
                'gaspoints_balance' => $customer->gaspoints_balance,
                'referral_code'   => $customer->referral_code,
                'member_since'    => $customer->created_at->format('F Y'),
                'total_orders'    => $customer->orders()->count(),
            ],
            'addresses'    => $addresses,
            'recentOrders' => $recentOrders,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $customer = auth('customer')->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $customer->update(['name' => strip_tags($validated['name'])]);

        return back()->with('success', 'Profile updated successfully.');
    }
}
