<?php

namespace App\Http\Controllers\Customer\GasPoints;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GasPointsTransaction;
use App\Services\GasPointsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GasPointsController extends Controller
{
    public function __construct(private readonly GasPointsService $gasPoints) {}

    public function index(Request $request): Response
    {
        $customer = auth('customer')->user();

        $transactions = GasPointsTransaction::where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn (GasPointsTransaction $transaction) => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'event_code' => $transaction->event_code,
                'points' => $transaction->points,
                'balance_after' => $transaction->balance_after,
                'remaining_points' => $transaction->remaining_points,
                'description' => $transaction->description,
                'order_id' => $transaction->order_id,
                'expires_at' => $transaction->expires_at?->toDateString(),
                'expired_at' => $transaction->expired_at?->toDateString(),
                'created_at' => $transaction->created_at->format('d M Y, g:i A'),
            ]);

        $referralCount = Customer::where('referred_by', $customer->id)->count();
        $config = $this->gasPoints->config();
        $tiers = array_map(fn ($tier) => [
            'label' => $tier['label'],
            'threshold' => $tier['points'],
            'description' => $tier['description'],
        ], $config['redemption_tiers']);

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
            'enabled' => $config['enabled'],
            'balance' => $currentBalance,
            'transactions' => $transactions,
            'tiers' => $tiers,
            'nextTier' => $nextTier,
            'referralCode' => $customer->referral_code,
            'referralCount' => $referralCount,
            'earnRates' => $config['earn_rates'],
            'rules' => $config['rules'],
        ]);
    }
}