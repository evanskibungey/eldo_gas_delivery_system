<?php

namespace App\Listeners;

use App\Events\DamagedCylinderReportedEvent;
use App\Jobs\SendSmsJob;
use App\Models\StockAuditLog;
use App\Models\StockLevel;
use App\Models\SystemSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleDamagedCylinder implements ShouldQueue
{
    public string $queue = 'high';

    public function handle(DamagedCylinderReportedEvent $event): void
    {
        $order = $event->order->load(['customer', 'size']);

        Log::critical("[P0] Damaged/unsafe cylinder reported — order #{$order->order_number} · size {$order->size_id} · customer {$order->customer_id}");

        // Audit trail — flag for batch inspection
        $stock = StockLevel::where('size_id', $order->size_id)->first();

        StockAuditLog::create([
            'size_id'       => $order->size_id,
            'change_type'   => 'damaged_cylinder_flag',
            'change_amount' => 0,
            'new_count'     => $stock?->filled_count ?? 0,
            'order_id'      => $order->id,
            'note'          => "Damaged/unsafe cylinder reported by customer for order #{$order->order_number}. Batch inspection required.",
            'created_at'    => now(),
        ]);

        // P0: Immediate SMS to shop manager — non-blocking via queue
        $shopPhone = config('shop.manager_phone', '');
        if ($shopPhone) {
            $customerName = $order->customer?->name ?? 'Unknown';
            $sizeName     = $order->size?->name     ?? 'Unknown';
            $appName = SystemSetting::get('app_name', 'EldoGas');
            $msg = "P0 SAFETY ALERT — {$appName}: Damaged/unsafe {$sizeName} cylinder reported by {$customerName}. "
                 . "Order #{$order->order_number}. Inspect batch immediately and call customer: {$order->customer?->phone}";

            SendSmsJob::dispatch($shopPhone, $msg, 'damaged_cylinder_p0', 'admin', 0);
        }
    }
}
