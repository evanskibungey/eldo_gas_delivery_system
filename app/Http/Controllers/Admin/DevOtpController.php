<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OtpToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Temporary OTP lookup tool for use before Africa's Talking credentials
 * are configured. Only active when AT_API_KEY is empty. Auto-disabled
 * in production once real SMS credentials are present.
 */
class DevOtpController extends Controller
{
    public function show(): Response
    {
        abort_if($this->smsActive(), 403, 'SMS is active — this tool is disabled.');

        return Inertia::render('Admin/Dev/OtpLookup');
    }

    public function lookup(Request $request): JsonResponse
    {
        abort_if($this->smsActive(), 403, 'SMS is active — this tool is disabled.');

        $phone = $request->string('phone')->trim()->toString();

        if (empty($phone)) {
            return response()->json(['otp' => null, 'message' => 'Phone is required.'], 422);
        }

        $token = OtpToken::where('phone', $phone)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->value('token');

        if (! $token) {
            return response()->json([
                'otp'     => null,
                'message' => 'No active OTP found for this number. Ask the user to request a new code.',
            ]);
        }

        return response()->json([
            'otp'     => $token,
            'message' => "Active OTP for {$phone}",
        ]);
    }

    private function smsActive(): bool
    {
        return ! empty(config('services.africastalking.api_key'));
    }
}
