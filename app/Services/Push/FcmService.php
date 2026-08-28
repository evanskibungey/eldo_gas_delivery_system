<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging transport (HTTP v1).
 *
 * The legacy endpoint (fcm.googleapis.com/fcm/send + "Authorization: key=")
 * was decommissioned by Google in July 2024, so this speaks the v1 API and
 * mints its own OAuth2 access token from the service-account credentials.
 *
 * When credentials are absent the service degrades to logging what it would
 * have sent, which keeps local development and CI free of Firebase setup.
 */
class FcmService
{
    private const TOKEN_CACHE_KEY = 'fcm:access_token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /**
     * @param  array<string, scalar|null>  $data  Data payload; FCM v1 requires string values.
     * @param  string|null  $androidChannelId
     *   Android notification channel to deliver on. Only pass a channel the
     *   receiving app creates at startup — on API 26+ Android silently drops a
     *   notification addressed to a channel that does not exist yet.
     *
     *   Omitting it means the app's default channel, which for a message with
     *   no declared channel is a low-importance one: shown in the tray, but no
     *   heads-up banner and no sound. Fine for a status update, not fine for a
     *   rider assignment on a 60-second timer.
     *
     * @throws FcmException when FCM rejects the message.
     */
    public function send(
        string $deviceToken,
        string $title,
        string $body,
        array $data = [],
        ?string $androidChannelId = null,
    ): void {
        $projectId = (string) config('services.firebase.project_id', '');
        $accessToken = $this->accessToken();

        if ($projectId === '' || $accessToken === null) {
            Log::info('[push] would send (FCM not configured)', [
                'token' => $this->maskToken($deviceToken),
                'title' => $title,
                'body'  => $body,
                'data'  => $data,
            ]);

            return;
        }

        $response = Http::timeout(10)
            ->withToken($accessToken)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token'        => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    // v1 rejects non-string data values outright.
                    'data'    => $this->stringifyData($data),
                    'android' => [
                        'priority'     => 'HIGH',
                        'notification' => array_filter([
                            'sound'      => 'default',
                            'channel_id' => $androidChannelId,
                        ], static fn ($value) => $value !== null),
                    ],
                    'apns' => [
                        'headers' => ['apns-priority' => '10'],
                        'payload' => ['aps' => ['sound' => 'default']],
                    ],
                ],
            ]);

        if ($response->successful()) {
            return;
        }

        throw new FcmException(
            'FCM send failed: HTTP ' . $response->status() . ' ' . $response->body(),
            $this->indicatesDeadToken($response->status(), (string) $response->json('error.status', '')),
        );
    }

    /**
     * Exchange the service-account key for a short-lived OAuth2 access token.
     * Cached just under the hour Google grants so we mint one per hour, not
     * one per notification.
     */
    private function accessToken(): ?string
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return null;
        }

        return Cache::remember(self::TOKEN_CACHE_KEY, 3300, function () use ($credentials): string {
            $now = time();

            $assertion = $this->signJwt([
                'iss'   => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud'   => self::TOKEN_ENDPOINT,
                'iat'   => $now,
                'exp'   => $now + 3600,
            ], $credentials['private_key']);

            $response = Http::asForm()->timeout(10)->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $assertion,
            ]);

            if (! $response->successful()) {
                throw new FcmException(
                    'FCM token exchange failed: HTTP ' . $response->status() . ' ' . $response->body(),
                );
            }

            $accessToken = (string) $response->json('access_token', '');

            if ($accessToken === '') {
                throw new FcmException('FCM token exchange returned no access_token.');
            }

            return $accessToken;
        });
    }

    /**
     * Service-account JSON, read either from a file path or from the raw
     * JSON itself — Forge environments often paste the credentials straight
     * into an env var rather than shipping a file.
     *
     * @return array{client_email: string, private_key: string}|null
     */
    private function credentials(): ?array
    {
        $configured = (string) config('services.firebase.credentials', '');

        if ($configured === '') {
            return null;
        }

        $raw = is_file($configured) ? (string) file_get_contents($configured) : $configured;
        $decoded = json_decode($raw, true);

        if (! is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
            Log::error('[push] FCM credentials are missing client_email/private_key.');

            return null;
        }

        return [
            'client_email' => (string) $decoded['client_email'],
            'private_key'  => (string) $decoded['private_key'],
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function signJwt(array $claims, string $privateKey): string
    {
        $segments = [
            $this->base64UrlEncode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64UrlEncode((string) json_encode($claims)),
        ];

        $signingInput = implode('.', $segments);
        $key = openssl_pkey_get_private($privateKey);

        if ($key === false) {
            throw new FcmException('FCM service-account private key could not be parsed.');
        }

        if (! openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new FcmException('Failed to sign the FCM service-account assertion.');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * UNREGISTERED (404) means the app was uninstalled or the token rotated;
     * INVALID_ARGUMENT (400) generally means a malformed token. Both are
     * permanent for that token, so the caller prunes rather than retries.
     */
    private function indicatesDeadToken(int $status, string $errorStatus): bool
    {
        return $status === 404
            || $errorStatus === 'NOT_FOUND'
            || $errorStatus === 'UNREGISTERED'
            || ($status === 400 && $errorStatus === 'INVALID_ARGUMENT');
    }

    /**
     * @param  array<string, scalar|null>  $data
     * @return array<string, string>
     */
    private function stringifyData(array $data): array
    {
        return array_map(static fn ($value): string => (string) $value, $data);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function maskToken(string $token): string
    {
        return substr($token, 0, 12) . '...';
    }
}
