<?php

namespace App\Http\Controllers\Customer\Order;

use App\Actions\ReportDamagedCylinderAction;
use App\Actions\ReportWrongCylinderAction;
use App\Actions\RiderNoShowAction;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderIssueController extends Controller
{
    // 9.2 — Customer reports wrong cylinder on active delivery
    public function wrongCylinder(Request $request, Order $order, ReportWrongCylinderAction $action): RedirectResponse
    {
        abort_unless(auth('customer')->user()->can('view', $order), 403);

        $request->validate(['description' => 'required|string|max:500']);

        $action->execute(
            $order,
            $request->input('description'),
            'customer',
            auth('customer')->id(),
        );

        return back()->with('success', 'Issue reported. A correction is now in progress.');
    }

    // 9.3 — Customer reports rider hasn't arrived
    public function riderNoShow(Request $request, Order $order, RiderNoShowAction $action): RedirectResponse
    {
        abort_unless(auth('customer')->user()->can('view', $order), 403);

        $action->execute($order, 'customer', auth('customer')->id());

        return back()->with('success', 'Report received. An admin will reassign your delivery.');
    }

    // 9.6 — Customer reports damaged or unsafe cylinder (P0)
    public function damagedCylinder(Request $request, Order $order, ReportDamagedCylinderAction $action): RedirectResponse
    {
        abort_unless(auth('customer')->user()->can('view', $order), 403);

        $request->validate(['description' => 'required|string|max:500']);

        $action->execute(
            $order,
            $request->input('description'),
            'customer',
            auth('customer')->id(),
        );

        return back()->with('success', 'Safety report received. Please keep the cylinder in a ventilated area away from heat sources. Our team has been alerted immediately.');
    }
}
