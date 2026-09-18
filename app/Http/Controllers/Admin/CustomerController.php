<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GasPointsTransaction;
use App\Services\Customer\CustomerRegistrar;
use App\Support\Utf8Sanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerRegistrar $registrar) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        // 'app' — has logged in through the app at least once; 'walk_in' — has
        // not. The latter is the list worth an SMS campaign, so it is a filter
        // rather than only a badge.
        $source = $request->string('source')->toString();

        $customers = Customer::query()
            ->when($search, fn ($q) =>
                $q->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                )
            )
            ->when($source === 'app', fn ($q) => $q->whereNotNull('phone_verified_at'))
            ->when($source === 'walk_in', fn ($q) => $q->whereNull('phone_verified_at'))
            ->withCount('orders')
            ->with(['orders' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($c) => [
                'id'               => $c->id,
                'name'             => $c->name,
                'phone'            => $c->phone,
                'gaspoints_balance' => $c->gaspoints_balance,
                'orders_count'     => $c->orders_count,
                'is_active'        => $c->is_active,
                // Whether they have ever signed into the app. OtpService
                // backfills phone_verified_at on the first successful login,
                // so a walk-in flips to an app user by themselves the day
                // they install it — no second column to keep in step.
                'is_app_user'      => $c->phone_verified_at !== null,
                'created_via'      => $c->created_via,
                'joined_at'        => $c->created_at->format('d M Y'),
                'last_order_at'    => $c->orders->first()?->created_at->diffForHumans(),
            ]);

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters'   => ['search' => $search, 'source' => $source],
        ]);
    }

    /**
     * Customer search for the admin composers.
     *
     * Shared by the SMS composer and the order composer — both are a "find the
     * two people I mean" box, so this is capped rather than paginated. Anyone
     * reaching for hundreds of recipients wants an audience filter instead,
     * which is one control away on that screen.
     *
     * Addresses come along only when asked for: the order composer needs
     * somewhere to deliver, the SMS composer does not, and this runs on every
     * keystroke.
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q'));

        $customers = Customer::query()
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->when($query !== '', fn ($q) => $q->where(
                fn ($inner) => $inner
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%"),
            ))
            ->when($request->boolean('with_addresses'), fn ($q) => $q->with([
                'addresses' => fn ($a) => $a->orderByDesc('is_default'),
            ]))
            // Newest first with no search: the people just added are the ones
            // most likely to be wanted.
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $withAddresses = $request->boolean('with_addresses');

        return response()->json(
            Utf8Sanitizer::clean($customers->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'opted_out' => $c->sms_opt_out_at !== null,
                'is_app_user' => $c->phone_verified_at !== null,
                ...($withAddresses ? ['addresses' => $c->addresses->map(fn ($a) => [
                    'id' => $a->id,
                    'label' => $a->label,
                    'description' => $a->description,
                    'is_default' => (bool) $a->is_default,
                ])->values()->all()] : []),
            ])->all()),
        );
    }

    /**
     * Add a customer by hand — someone who phoned in or walked up to the
     * counter and has never used the app.
     *
     * Goes through CustomerRegistrar so they come into existence exactly as an
     * OTP signup does: same phone normalisation, same referral code. A number
     * already on file resolves to that record rather than colliding with the
     * UNIQUE phone column, which is what a repeat walk-in is.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $customer = $this->registrar->findOrCreateByPhone($data['phone'], $data['name'], 'admin');

        // The order composer creates the caller before it can attach a delivery
        // address to them, so it needs the record back rather than a redirect.
        if ($request->wantsJson()) {
            return response()->json(Utf8Sanitizer::clean([
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'opted_out' => $customer->sms_opt_out_at !== null,
                'is_app_user' => $customer->phone_verified_at !== null,
                'addresses' => $customer->addresses()->orderByDesc('is_default')->get()
                    ->map(fn ($a) => [
                        'id' => $a->id,
                        'label' => $a->label,
                        'description' => $a->description,
                        'is_default' => (bool) $a->is_default,
                    ])->values()->all(),
            ]), 201);
        }

        return back()->with('success', "{$customer->name} added.");
    }

    /**
     * A delivery address for a customer who has none.
     *
     * Coordinates are required because customer_addresses stores them NOT NULL
     * and the rider's map link is built from them — a description alone would
     * save a row nobody can navigate to. The composer gets them from the
     * geocoder (Admin\GeocodeController) rather than asking anyone to type
     * latitude and longitude.
     */
    public function storeAddress(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'in:Home,Office,Restaurant,Other'],
            'description' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $isFirst = $customer->addresses()->count() === 0;

        $address = $customer->addresses()->create([
            ...$data,
            'is_default' => $isFirst,
        ]);

        return response()->json([
            'id' => $address->id,
            'label' => $address->label,
            'description' => $address->description,
            'is_default' => (bool) $address->is_default,
        ], 201);
    }

    public function show(Customer $customer): Response
    {
        $customer->load([
            'addresses',
            'referrer',
            'referrals',
            'orders' => fn ($q) => $q->latest()
                ->with(['size', 'brand', 'rider', 'items.size:id,name', 'items.brand:id,name'])
                ->limit(10),
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
            // Every cylinder, so a basket in someone's history does not read
            // as the single cylinder that happened to be first.
            'items_summary' => $o->itemsSummary(),
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
