<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreAddressRequest;
use App\Http\Requests\Customer\StoreDetectedAddressRequest;
use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAddressController extends Controller
{
    public function index(): Response
    {
        $customer = auth('customer')->user();

        return Inertia::render('Customer/Address/Index', [
            'addresses' => $customer->addresses()->orderByDesc('is_default')->get(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Customer/Onboarding/SetLocation', [
            'isOnboarding' => false,
            'redirect_to'  => $request->query('redirect_to', ''),
        ]);
    }

    public function store(StoreAddressRequest $request): RedirectResponse
    {
        $customer  = auth('customer')->user();
        $isFirst   = $customer->addresses()->count() === 0;
        $makeDefault = $isFirst || $request->boolean('is_default');

        if ($makeDefault && ! $isFirst) {
            $customer->addresses()->update(['is_default' => false]);
        }

        $customer->addresses()->create([
            ...$request->validated(),
            'is_default' => $makeDefault,
        ]);

        return match ($request->query('redirect_to')) {
            'order_review' => redirect()->route('customer.order.review'),
            'order_new'    => redirect()->route('customer.order.build'),
            default        => redirect()->route('customer.addresses.index')->with('success', 'Address saved.'),
        };
    }

    public function update(StoreAddressRequest $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorizeAddress($address);

        $customer = auth('customer')->user();

        if ($request->boolean('is_default')) {
            $customer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($request->validated());

        return redirect()->route('customer.addresses.index')
            ->with('success', 'Address updated.');
    }

    public function destroy(CustomerAddress $address): RedirectResponse
    {
        $this->authorizeAddress($address);
        $address->delete();

        return redirect()->route('customer.addresses.index')
            ->with('success', 'Address removed.');
    }

    public function storeDetected(StoreDetectedAddressRequest $request): JsonResponse
    {
        $customer  = auth('customer')->user();
        $isFirst   = $customer->addresses()->count() === 0;

        if ($isFirst) {
            $customer->addresses()->update(['is_default' => false]);
        }

        $address = $customer->addresses()->create([
            ...$request->validated(),
            'is_default' => $isFirst,
        ]);

        return response()->json([
            'id'          => $address->id,
            'label'       => $address->label,
            'description' => $address->description,
            'is_default'  => $address->is_default,
        ], 201);
    }

    public function setDefault(CustomerAddress $address): RedirectResponse
    {
        $this->authorizeAddress($address);
        $customer = auth('customer')->user();

        $customer->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return back()->with('success', 'Default address updated.');
    }

    private function authorizeAddress(CustomerAddress $address): void
    {
        if ($address->customer_id !== auth('customer')->id()) {
            abort(403);
        }
    }
}
