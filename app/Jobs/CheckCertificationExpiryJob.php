<?php

namespace App\Jobs;

use App\Models\Rider;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckCertificationExpiryJob implements ShouldQueue
{
    use Queueable;

    public function handle(SmsServiceInterface $sms): void
    {
        // Riders marked as certified but whose certification_date is over 12 months ago
        $expired = Rider::where('is_safety_certified', true)
            ->whereNotNull('certification_date')
            ->where('certification_date', '<=', now()->subYear())
            ->get();

        if ($expired->isEmpty()) {
            return;
        }

        // Flag them as no longer certified
        Rider::whereIn('id', $expired->pluck('id'))
            ->update(['is_safety_certified' => false]);

        $names   = $expired->pluck('name')->implode(', ');
        $count   = $expired->count();

        Log::warning('[CertificationExpiry] Riders flagged as expired', [
            'count'  => $count,
            'riders' => $expired->pluck('id')->toArray(),
        ]);

        $managerPhone = config('shop.manager_phone');

        if ($managerPhone) {
            $message = "EldoGas Alert: {$count} rider(s) have expired safety certifications "
                . "and have been flagged: {$names}. Please schedule re-certification.";

            $sms->send($managerPhone, $message);
        }
    }
}
