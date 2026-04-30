<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AfricasTalkingSmsService implements SmsServiceInterface
{
    private string $apiKey;
    private string $username;
    private string $senderId;
    private bool   $devMode;

    public function __construct()
    {
        $this->apiKey   = config('services.africastalking.api_key', '');
        $this->username = config('services.africastalking.username', 'sandbox');
        $this->senderId = config('services.africastalking.sender_id', 'EldoGas');
        $this->devMode  = empty($this->apiKey) || app()->isLocal();
    }

    public function send(string $phone, string $message): bool
    {
        // In dev mode (or no API key), just log — never block the queue
        if ($this->devMode) {
            Log::channel('single')->info("[SMS:DEV] To: {$phone} | {$message}");
            return true;
        }

        try {
            $response = Http::withHeaders([
                'apiKey' => $this->apiKey,
                'Accept' => 'application/json',
            ])
            ->asForm()
            ->timeout(10)
            ->post('https://api.africastalking.com/version1/messaging', [
                'username' => $this->username,
                'to'       => $phone,
                'message'  => $message,
                'from'     => $this->senderId,
            ]);

            $data = $response->json();

            $status = $data['SMSMessageData']['Recipients'][0]['status'] ?? 'Unknown';

            if ($status === 'Success') {
                return true;
            }

            Log::warning("[SMS] Failed sending to {$phone}: {$status}", $data);
            return false;

        } catch (\Throwable $e) {
            Log::error("[SMS] Exception sending to {$phone}: {$e->getMessage()}");
            return false;
        }
    }
}
