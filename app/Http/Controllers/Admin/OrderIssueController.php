<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ReportOutOfStockAction;
use App\Actions\ResolvePaymentDisputeAction;
use App\Events\PaymentDisputeEvent;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Admin\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderIssueController extends Controller
{
    // 9.1 - Admin marks order as out of stock
    public function outOfStock(Request $request, Order $order, ReportOutOfStockAction $action): RedirectResponse
    {
        $request->validate(['reason' => 'required|string|max:255']);

        $action->execute($order, $request->input('reason'), auth('admin')->id());

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Order cancelled as out of stock.');
    }

    // 9.4 - Flag payment dispute (admin or via customer escalation)
    public function flagPaymentDispute(Request $request, Order $order): RedirectResponse
    {
        if ($order->payment_status === 'disputed') {
            return back()->with('error', 'Payment dispute already flagged.');
        }

        $order->update([
            'payment_status'    => 'disputed',
            'has_issue'         => true,
            'issue_type'        => 'payment_dispute',
            'issue_description' => $request->input('note'),
        ]);

        OrderStatusHistory::create([
            'order_id'   => $order->id,
            'status'     => $order->status,
            'note'       => 'Payment dispute flagged' . ($request->input('note') ? ': ' . $request->input('note') : ''),
            'actor_type' => 'admin',
            'actor_id'   => auth('admin')->id(),
            'created_at' => now(),
        ]);

        event(new PaymentDisputeEvent($order));

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Payment dispute flagged.');
    }

    // 9.4 - Admin resolves payment dispute
    public function resolvePaymentDispute(Request $request, Order $order, ResolvePaymentDisputeAction $action): RedirectResponse
    {
        $request->validate(['resolution' => 'required|in:paid,refund']);

        $action->execute($order, $request->input('resolution'), auth('admin')->id());

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Payment dispute resolved.');
    }

    // Resume delivery after correction_in_progress
    public function resolveCorrection(Order $order, OrderService $orders): RedirectResponse
    {
        try {
            $orders->resolveCorrection($order);
        } catch (ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Issue resolved. Delivery resumed.');
    }
}

