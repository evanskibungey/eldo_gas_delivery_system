<?php

namespace App\Http\Controllers\Customer\GasPoints;

use App\Http\Controllers\Controller;
use App\Models\GasPointsTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GasPointsController extends Controller
{
    public function index(Request $request): Response
    {
        $customer = auth('customer')->user();

        $transactions = GasPointsTransaction::where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn ($t) => [
                'id'          => $t->id,
                'type'        => $t->type,
                'points'      => $t->points,
                'balance_after' => $t->balance_after,
                'description' => $t->description,
                'order_id'    => $t->order_id,
                'created_at'  => $t->created_at->format('d M Y, g:i A'),
            ]);

        $referralCount = \App\Models\Customer::where('referred_by', $customer->id)->count();

        $tiers = [
            ['label' => 'Bronze',   'threshold' => 500,   'description' => 'KES 50 off your next order'],
            ['label' => 'Silver',   'threshold' => 1000,  'description' => 'KES 100 off your next order'],
            ['label' => 'Gold',     'threshold' => 2000,  'description' => 'KES 200 off your next order'],
            ['label' => 'Platinum', 'threshold' => 5000,  'description' => 'KES 500 off your next order'],
        ];

        $currentBalance = (int) $customer->gaspoints_balance;
        $nextTier = null;
        foreach ($tiers as $tier) {
            if ($currentBalance < $tier['threshold']) {
                $nextTier = $tier;
                $nextTier['progress'] = $currentBalance;
                break;
            }
        }

        return Inertia::render('Customer/GasPoints/Index', [
            'balance'       => $currentBalance,
            'transactions'  => $transactions,
            'tiers'         => $tiers,
            'nextTier'      => $nextTier,
            'referralCode'  => $customer->referral_code,
            'referralCount' => $referralCount,
        ]);
    }
}
