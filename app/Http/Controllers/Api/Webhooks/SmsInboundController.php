<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Inbound SMS from the gateway, used to honour STOP.
 *
 * Point TalkSasa's inbound/callback URL at POST /api/v1/webhooks/sms/inbound.
 * Field names differ between providers, so several spellings are accepted
 * rather than assuming one.
 *
 * Until that URL is configured this endpoint simply never fires, and opt-outs
 * are recorded by hand from the customer page instead. Nothing breaks either
 * way — which is why it does not assume it is wired up.
 */
class SmsInboundController extends Controller
{
    /** Anything a person plausibly texts to stop marketing. */
    private const STOP_WORDS = ['stop', 'unsubscribe', 'opt out', 'optout', 'end', 'quit', 'cancel'];

    public function handle(Request $request): JsonResponse
    {
        $from = (string) ($request->input('from')
            ?? $request->input('sender')
            ?? $request->input('msisdn')
            ?? $request->input('phone')
            ?? '');

        $text = strtolower(trim((string) ($request->input('message')
            ?? $request->input('text')
            ?? $request->input('body')
            ?? '')));

        if ($from === '' || $text === '') {
            // Acknowledge regardless: a gateway that gets an error will retry
            // this forever.
            return response()->json(['status' => 'ignored']);
        }

        if (! $this->isStop($text)) {
            Log::info('[SMS] Inbound message ignored (not an opt-out)', ['from' => $from]);

            return response()->json(['status' => 'ignored']);
        }

        $customer = $this->findByPhone($from);

        if (! $customer) {
            Log::warning('[SMS] STOP from an unknown number', ['from' => $from]);

            return response()->json(['status' => 'unknown']);
        }

        if ($customer->sms_opt_out_at === null) {
            $customer->update([
                'sms_opt_out_at' => now(),
                'sms_opt_out_source' => 'sms',
            ]);

            Log::info("[SMS] Customer #{$customer->id} opted out by SMS");
        }

        return response()->json(['status' => 'opted_out']);
    }

    private function isStop(string $text): bool
    {
        // Exact-ish match only. "Please don't stop delivering" is not an
        // opt-out, and silently dropping a good customer is worse than missing
        // one unusual phrasing.
        return in_array($text, self::STOP_WORDS, true);
    }

    /**
     * Numbers arrive in whatever shape the gateway uses: +254…, 254…, 07….
     * Compare on the last nine digits, which are stable across all three.
     */
    private function findByPhone(string $phone): ?Customer
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        $tail = substr($digits, -9);

        if (strlen($tail) < 9) {
            return null;
        }

        return Customer::where('phone', 'like', "%{$tail}")->first();
    }
}
