<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TalkSasaSmsService implements SmsServiceInterface
{
    public function send(string $phone, string $message): bool
    {
        $apiToken = config('services.talksasa.api_token');

        if (! $apiToken) {
            Log::channel('single')->info("[SMS:DEV] To: {$phone} | {$message}");
            return true;
        }

        try {
            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->timeout(10)
                ->post(config('services.talksasa.api_url'), [
                    'recipient' => $phone,
                    'sender_id' => config('services.talksasa.sender_id'),
                    'type'      => 'plain',
                    'message'   => $message,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('[SMS] TalkSasa send failed', [
                'phone'  => $phone,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return false;

        } catch (\Throwable $e) {
            Log::error("[SMS] Exception sending to {$phone}: {$e->getMessage()}");
            return false;
        }
    }
}
