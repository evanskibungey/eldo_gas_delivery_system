<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendOtpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly string $phone,
        private readonly string $token,
    ) {}

    public function handle(): void
    {
        $apiKey   = config('services.africastalking.api_key');
        $username = config('services.africastalking.username');

        $appName = SystemSetting::get('app_name', 'EldoGas');
        $message = "Your {$appName} code is: {$this->token}. Valid for 10 minutes. Do not share.";

        if (! $apiKey) {
            Log::channel('single')->info("[OTP] {$this->phone} → {$this->token}");
            return;
        }

        Http::withHeaders(['apiKey' => $apiKey, 'Accept' => 'application/json'])
            ->asForm()
            ->post('https://api.africastalking.com/version1/messaging', [
                'username' => $username,
                'to'       => $this->phone,
                'message'  => $message,
                'from'     => config('services.africastalking.sender_id'),
            ]);
    }
}
