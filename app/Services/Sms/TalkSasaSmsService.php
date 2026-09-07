<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TalkSasaSmsService implements SmsServiceInterface
{
    /**
     * Why the last send failed, for the caller to record.
     *
     * The interface returns a bool, so without this the reason only ever
     * reached the log file — leaving notifications_log saying nothing more
     * useful than "gateway returned failure".
     */
    private ?string $lastError = null;

    public function send(string $phone, string $message): bool
    {
        $this->lastError = null;

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
                    'type' => 'plain',
                    'message' => $message,
                ]);

            if ($this->accepted($response)) {
                return true;
            }

            $this->lastError = $this->errorFrom($response);

            Log::warning('[SMS] TalkSasa send failed', [
                'phone' => $phone,
                'sender_id' => config('services.talksasa.sender_id'),
                'status' => $response->status(),
                'error' => $this->lastError,
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::error("[SMS] Exception sending to {$phone}: {$e->getMessage()}");

            return false;
        }
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * TalkSasa answers a rejected message with HTTP 200 and an error in the
     * body, so the HTTP status alone says almost nothing.
     *
     * This is what hid an unregistered sender ID: the send was refused, the
     * response was 200, every message was marked delivered, and nothing was
     * logged. Treat the payload's own status as the answer and fall back to
     * the HTTP code only when the body is not JSON.
     */
    private function accepted(Response $response): bool
    {
        if ($response->failed()) {
            return false;
        }

        $body = $response->json();

        if (! is_array($body)) {
            // Not JSON — a proxy error page or an empty body. A 2xx is the only
            // evidence available.
            return $response->successful();
        }

        $status = strtolower((string) ($body['status'] ?? ''));

        // Some endpoints omit `status` entirely on success, so an absent field
        // is not treated as a failure — only an explicitly bad one is.
        return ! in_array($status, ['error', 'fail', 'failed', 'false'], true);
    }

    private function errorFrom(Response $response): string
    {
        $body = $response->json();

        if (is_array($body)) {
            foreach (['message', 'error', 'data'] as $key) {
                if (! empty($body[$key]) && is_string($body[$key])) {
                    return $body[$key];
                }
            }
        }

        return 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 200);
    }
}
