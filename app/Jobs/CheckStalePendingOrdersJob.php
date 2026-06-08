<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\SystemSetting;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Alerts admin when orders have been in `pending` status beyond the configured
 * threshold, meaning auto-assign found no eligible rider and manual intervention
 * is required. Fires at most once per order (tracked via status history note).
 */
class CheckStalePendingOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $thresholdMinutes = (int) SystemSetting::get('stale_pending_alert_minutes', 10);

        $stale = Order::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes($thresholdMinutes))
            ->whereDoesntHave('statusHistory', function ($q): void {
                $q->where('note', 'like', '%stale_pending_alerted%');
            })
            ->with(['customer:id,name', 'size:id,name', 'brand:id,name'])
            ->get();

        if ($stale->isEmpty()) {
            return;
        }

        $phones   = $this->resolveManagerPhones();
        $template = app(SmsTemplateService::class);

        foreach ($stale as $order) {
            // Record so this order is not alerted again.
            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $order->status,
                'note'       => "stale_pending_alerted after {$thresholdMinutes}min",
                'actor_type' => 'system',
                'actor_id'   => null,
                'created_at' => now(),
            ]);

            if (empty($phones)) {
                Log::warning("[StalePending] No admin phones configured — skipping SMS for order #{$order->order_number}");
                continue;
            }

            $message = $template->adminNoRiderAvailable($order);

            foreach ($phones as $phone) {
                SendSmsJob::dispatch($phone, $message, 'stale_pending_alert', 'admin', 0);
            }

            Log::info("[StalePending] Alert sent for order #{$order->order_number} (pending {$thresholdMinutes}+ min).");
        }
    }

    private function resolveManagerPhones(): array
    {
        $raw = config('shop.manager_phones', '');

        if (empty($raw)) {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(function (string $p): string {
                $p = trim($p);
                return str_starts_with($p, '0') ? '+254' . substr($p, 1) : $p;
            })
            ->filter()
            ->values()
            ->all();
    }
}
