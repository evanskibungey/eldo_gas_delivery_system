<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer in-app inbox. Reads from the existing `notifications_log`
 * table (filtered to the current customer) and supports marking
 * everything as read in one call.
 */
class NotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();

        $paginator = NotificationLog::query()
            ->where('recipient_type', 'customer')
            ->where('recipient_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate(25);

        $items = $paginator->through(fn (NotificationLog $n) => [
            'id'         => $n->id,
            'trigger'    => $n->trigger,
            'channel'    => $n->channel,
            'title'      => $n->title,
            'message'    => $n->message,
            'data'       => $n->data,
            'created_at' => $n->created_at?->toIso8601String(),
            'read_at'    => $n->read_at?->toIso8601String(),
        ])->items();

        $unread = NotificationLog::query()
            ->where('recipient_type', 'customer')
            ->where('recipient_id', $customer->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'unread' => $unread,
            'data'   => $items,
            'meta'   => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = NotificationLog::query()
            ->where('recipient_type', 'customer')
            ->where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Marked as read.',
            'updated' => $updated,
        ]);
    }
}
