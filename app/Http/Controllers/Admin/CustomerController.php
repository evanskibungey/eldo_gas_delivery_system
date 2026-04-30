<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GasPointsTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $customers = Customer::query()
            ->when($search, fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
            )
            ->withCount('orders')
            ->with(['orders' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->through(fn ($c) => [
                'id'               => $c->id,
                'name'             => $c->name,
                'phone'            => $c->phone,
                'gaspoints_balance' => $c->gaspoints_balance,
                'orders_count'     => $c->orders_count,
                'is_active'        => $c->is_active,
                'joined_at'        => $c->created_at->format('d M Y'),
                'last_order_at'    => $c->orders->first()?->created_at->diffForHumans(),
            ]);

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters'   => ['search' => $search],
        ]);
    }

    public function show(Customer $customer): Response
    {
        $customer->load([
            'addresses',
            'referrer',
            'referrals',
            'orders' => fn ($q) => $q->latest()->with(['size', 'brand', 'rider'])->limit(10),
            'gasPointsTransactions' => fn ($q) => $q->orderByDesc('created_at')->limit(20),
        ]);

        $totalSpend = $customer->orders()->where('status', 'delivered')->sum('total_amount');
        $referralCount = $customer->referrals()->count();

        $orders = $customer->orders->map(fn ($o) => [
            'id'           => $o->id,
            'order_number' => $o->order_number,
            'status'       => $o->status,
            'order_type'   => $o->order_type,
            'size_name'    => $o->size?->name,
            'brand_name'   => $o->brand?->name,
            'rider_name'   => $o->rider?->name,
            'total_amount' => $o->total_amount,
            'payment_method' => $o->payment_method,
            'created_at'   => $o->created_at->format('d M Y, g:i A'),
        ]);

        $transactions = $customer->gasPointsTransactions->map(fn ($t) => [
            'id'           => $t->id,
            'type'         => $t->type,
            'points'       => $t->points,
            'balance_after' => $t->balance_after,
            'description'  => $t->description,
            'created_at'   => $t->created_at->format('d M Y'),
        ]);

        $addresses = $customer->addresses->map(fn ($a) => [
            'id'          => $a->id,
            'label'       => $a->label,
            'description' => $a->description,
            'is_default'  => (bool) $a->is_default,
        ]);

        return Inertia::render('Admin/Customers/Show', [
            'customer' => [
                'id'               => $customer->id,
                'name'             => $customer->name,
                'phone'            => $customer->phone,
                'is_active'        => $customer->is_active,
                'gaspoints_balance' => $customer->gaspoints_balance,
                'referral_code'    => $customer->referral_code,
                'referred_by_name' => $customer->referrer?->name,
                'referral_count'   => $referralCount,
                'total_spend'      => (int) $totalSpend,
                'total_orders'     => $customer->orders()->count(),
                'member_since'     => $customer->created_at->format('d M Y'),
            ],
            'orders'       => $orders,
            'transactions' => $transactions,
            'addresses'    => $addresses,
        ]);
    }
}
