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
        $apiToken = config('services.talksasa.api_token');
        $appName  = SystemSetting::get('app_name', 'EldoGas');
        $message  = "Your {$appName} code is: {$this->token}. Valid for 10 minutes. Do not share.";

        if (! $apiToken) {
            Log::channel('single')->info("[OTP] {$this->phone} → {$this->token}");
            return;
        }

        $response = Http::withToken($apiToken)
            ->acceptJson()
            ->post(config('services.talksasa.api_url'), [
                'recipient' => $this->phone,
                'sender_id' => config('services.talksasa.sender_id'),
                'type'      => 'plain',
                'message'   => $message,
            ]);

        if (! $response->successful()) {
            Log::error('[OTP] TalkSasa send failed', [
                'phone'  => $this->phone,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            $this->fail("TalkSasa returned {$response->status()}");
        }
    }
}
